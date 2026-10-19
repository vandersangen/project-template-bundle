<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service;

use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use LogicException;
use Psr\Cache\InvalidArgumentException;
use Throwable;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagSettingRepository;

/**
 * Whether a feature flag is on for an audience.
 *
 * Only a stored state can switch a flag on: a flag never switched for an audience is off for it, in every
 * environment and in the tests. The stored states are cached in the app cache and cleared on every change of a
 * setting (FeatureFlagCacheInvalidationListener, and after each switch here), so a switch counts at once, also in a
 * worker.
 */
final readonly class FeatureFlagService
{
    private const string CACHE_KEY = 'project_template_feature_flag_states';

    /**
     * A backstop only: every change clears the cache at once. Should a clear race a transaction that was not
     * committed yet, the stale states last at most this long.
     */
    private const int CACHE_LIFETIME_SECONDS = 3600;

    public function __construct(
        private FeatureFlagSettingRepository $featureFlagSettingRepository,
        private FeatureFlagRegistry $featureFlagRegistry,
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
    ) {
    }

    /**
     * A flag or audience the project did not configure is off, even when a row for it is stored.
     *
     * @param FeatureFlagInterface         $featureFlag The flag.
     * @param FeatureFlagAudienceInterface $audience    The audience whose switch counts.
     *
     * @return bool
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function isOnFor(FeatureFlagInterface $featureFlag, FeatureFlagAudienceInterface $audience): bool
    {
        if ($this->featureFlagRegistry->findFlag($featureFlag->key()) === null) {
            return false;
        }

        if ($this->featureFlagRegistry->findAudience($audience->key()) === null) {
            return false;
        }

        return $this->findStoredStates()[$featureFlag->key()][$audience->key()] ?? false;
    }

    /**
     * For a cron or queue handler, which has no signed-in user: the flag's background audience decides.
     *
     * @param FeatureFlagInterface $featureFlag The flag.
     *
     * @return bool
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function isOnForBackgroundWork(FeatureFlagInterface $featureFlag): bool
    {
        return $this->isOnFor($featureFlag, $featureFlag->backgroundAudience());
    }

    /**
     * @param FeatureFlagAudienceInterface $audience The audience whose switches count.
     *
     * @return list<FeatureFlagInterface> The configured flags that are on for the audience, in enum order.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function listFlagsOnFor(FeatureFlagAudienceInterface $audience): array
    {
        $flagsOn = [];

        foreach ($this->featureFlagRegistry->flags() as $featureFlag) {
            if ($this->isOnFor($featureFlag, $audience)) {
                $flagsOn[] = $featureFlag;
            }
        }

        return $flagsOn;
    }

    /**
     * Stores the switch and clears the cached states. When the state changes it flushes the entity manager, so
     * whatever else is pending in the unit of work is written with it; the same state again flushes nothing.
     *
     * The row is first reserved with INSERT IGNORE (off, which means the same as no row), so a first switch by two
     * processes at once cannot fail on the unique key. It is then read with a write lock and switched through the
     * ORM, so entity listeners see an update.
     *
     * @param FeatureFlagInterface         $featureFlag The flag.
     * @param FeatureFlagAudienceInterface $audience    The audience whose switch changes.
     * @param bool                         $on          Whether the flag is on for the audience from now on.
     * @param string                       $changedBy   Who switched it, such as the user identifier.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     * @throws Throwable                When storing fails; the transaction is rolled back.
     */
    public function switchFlag(
        FeatureFlagInterface $featureFlag,
        FeatureFlagAudienceInterface $audience,
        bool $on,
        string $changedBy,
    ): void {
        $this->reserveSettingRow($featureFlag->key(), $audience->key(), $changedBy);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->applySwitchToLockedSetting($featureFlag->key(), $audience->key(), $on, $changedBy);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        $this->clearCachedStates();
    }

    /**
     * Drops the cached states, so the next check reads the database.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function clearCachedStates(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /**
     * Makes sure the row of the flag and audience exists, off when it is new. An existing row is left alone.
     *
     * @param string $flagKey     The key of the flag.
     * @param string $audienceKey The key of the audience.
     * @param string $changedBy   Who switched it.
     */
    private function reserveSettingRow(string $flagKey, string $audienceKey, string $changedBy): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'INSERT IGNORE INTO feature_flags (feature_flag, audience, is_on, updated_at, updated_by)
                VALUES (:flag, :audience, 0, :now, :by)',
            [
                'flag' => $flagKey,
                'audience' => $audienceKey,
                'now' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
                'by' => $changedBy,
            ],
        );
    }

    /**
     * Reads the reserved row with a write lock and switches it when its state differs; inside the transaction of
     * switchFlag().
     *
     * @param string $flagKey     The key of the flag.
     * @param string $audienceKey The key of the audience.
     * @param bool   $on          Whether the flag is on for the audience from now on.
     * @param string $changedBy   Who switched it.
     *
     * @throws LogicException When the reserved row is gone.
     */
    private function applySwitchToLockedSetting(string $flagKey, string $audienceKey, bool $on, string $changedBy): void
    {
        $setting = $this->featureFlagSettingRepository->findSettingOfForUpdate($flagKey, $audienceKey)
            ?? throw new LogicException(sprintf('No feature_flags row for %s and %s.', $flagKey, $audienceKey));

        if ($setting->isOn() === $on) {
            return;
        }

        $setting->switchTo($on, $changedBy);
        $this->entityManager->flush();
    }

    /**
     * @return array<string, array<string, bool>>
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    private function findStoredStates(): array
    {
        return $this->cache->get(self::CACHE_KEY, $this->readStoredStatesForCache(...));
    }

    /**
     * @param ItemInterface $item The cache item the states are stored in.
     *
     * @return array<string, array<string, bool>>
     */
    private function readStoredStatesForCache(ItemInterface $item): array
    {
        $item->expiresAfter(self::CACHE_LIFETIME_SECONDS);

        return $this->featureFlagSettingRepository->findStoredStates();
    }
}
