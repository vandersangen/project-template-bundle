<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagSetting;
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
        private ManagerRegistry $managerRegistry,
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
     * Stores the switch and clears the cached states. It flushes the entity manager, so whatever else is pending in
     * the unit of work is written with it.
     *
     * @param FeatureFlagInterface         $featureFlag The flag.
     * @param FeatureFlagAudienceInterface $audience    The audience whose switch changes.
     * @param bool                         $on          Whether the flag is on for the audience from now on.
     * @param string                       $changedBy   Who switched it, such as the user identifier.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function switchFlag(
        FeatureFlagInterface $featureFlag,
        FeatureFlagAudienceInterface $audience,
        bool $on,
        string $changedBy,
    ): void {
        $setting = $this->featureFlagSettingRepository->findSettingOf($featureFlag->key(), $audience->key());

        if ($setting === null) {
            $setting = $this->insertFirstSetting($featureFlag, $audience, $on, $changedBy);
        }

        if ($setting === null || $setting->isOn() === $on) {
            $this->clearCachedStates();

            return;
        }

        $setting->switchTo($on, $changedBy);
        $this->entityManager->flush();
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
     * Stores the first switch of a flag for an audience.
     *
     * When another process stored it between our read and our insert, the unique key refuses ours and the failed
     * flush closes the entity manager. It is then reset (the service keeps the same, lazy, instance) and the row of
     * the other process is returned, for the caller to apply the switch to it.
     *
     * @param FeatureFlagInterface         $featureFlag The flag.
     * @param FeatureFlagAudienceInterface $audience    The audience whose switch changes.
     * @param bool                         $on          Whether the flag is on for the audience from now on.
     * @param string                       $changedBy   Who switched it.
     *
     * @return FeatureFlagSetting|null Null when our row was stored; the row of the other process otherwise.
     *
     * @throws UniqueConstraintViolationException When the other row cannot be found after all.
     */
    private function insertFirstSetting(
        FeatureFlagInterface $featureFlag,
        FeatureFlagAudienceInterface $audience,
        bool $on,
        string $changedBy,
    ): ?FeatureFlagSetting {
        try {
            $setting = new FeatureFlagSetting($featureFlag->key(), $audience->key(), $on, $changedBy);
            $this->entityManager->persist($setting);
            $this->entityManager->flush();

            return null;
        } catch (UniqueConstraintViolationException $exception) {
            if (!$this->entityManager->isOpen()) {
                $this->managerRegistry->resetManager();
            }

            return $this->featureFlagSettingRepository->findSettingOf($featureFlag->key(), $audience->key())
                ?? throw $exception;
        }
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
