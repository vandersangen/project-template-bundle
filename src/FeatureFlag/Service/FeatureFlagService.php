<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Exception\UnknownFeatureFlagException;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagAudienceRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagRepository;

/**
 * Whether a feature flag is on: for the current caller, or for an audience a cron or queue handler names.
 *
 * Only a stored switch can turn a flag on: a flag never switched for an audience is off for it, and so is an unknown
 * flag or audience, in every environment and in the tests. All states are one cache item, read once per request or
 * message and kept in memory until kernel.reset; it is cleared only after a change is committed
 * (FeatureFlagCacheInvalidationListener, and after each switch here).
 */
final class FeatureFlagService implements ResetInterface
{
    private const string CACHE_KEY = 'project_template_feature_flag_states';

    /**
     * A backstop only: every committed change clears the cache. Should a clear race a commit, the stale states last
     * at most this long.
     */
    private const int CACHE_LIFETIME_SECONDS = 3600;

    /** The length of feature_flag_audiences.updated_by. */
    private const int MAX_UPDATED_BY_LENGTH = 180;

    /** @var array<string, array<string, bool>>|null The states of this request or message, once read. */
    private ?array $states = null;

    /**
     * @param FeatureFlagRepository                     $featureFlagRepository         The flags.
     * @param FeatureFlagAudienceRepository             $featureFlagAudienceRepository The switches per audience.
     * @param FeatureFlagAudienceRegistry               $featureFlagAudienceRegistry   The configured audiences.
     * @param EntityManagerInterface                    $entityManager                 Writes a switch.
     * @param CacheInterface                            $cache                         Holds the states (cache.app).
     * @param Security                                  $security                      The signed-in user, if any.
     * @param FeatureFlagAudienceResolverInterface|null $featureFlagAudienceResolver   The project's resolver; null
     *                                                                                 without audiences.
     */
    public function __construct(
        private readonly FeatureFlagRepository $featureFlagRepository,
        private readonly FeatureFlagAudienceRepository $featureFlagAudienceRepository,
        private readonly FeatureFlagAudienceRegistry $featureFlagAudienceRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly Security $security,
        private readonly ?FeatureFlagAudienceResolverInterface $featureFlagAudienceResolver,
    ) {
    }

    /**
     * For the current caller: the signed-in user's audiences, or the guest audience for a visitor without login.
     * With more than one audience the flag is on when it is on for one of them.
     *
     * @param string $cid The cid of the flag.
     *
     * @return bool
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function isOn(string $cid): bool
    {
        return $this->isOnForOneOf($cid, $this->findAudiencesOfCaller());
    }

    /**
     * For a cron or queue handler, which has no caller: it names the audience whose switch counts.
     *
     * @param string $cid      The cid of the flag.
     * @param string $audience The key of the audience.
     *
     * @return bool Off for an audience the project did not configure, even when a row for it is stored.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function isOnFor(string $cid, string $audience): bool
    {
        if (!$this->featureFlagAudienceRegistry->hasAudience($audience)) {
            return false;
        }

        return $this->findStates()[$cid][$audience] ?? false;
    }

    /**
     * @return list<string> The cids of the flags on for the current caller, sorted.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function listCidsOn(): array
    {
        $audiencesOfCaller = $this->findAudiencesOfCaller();
        $cidsOn = [];

        foreach (array_keys($this->findStates()) as $cid) {
            if ($this->isOnForOneOf((string) $cid, $audiencesOfCaller)) {
                $cidsOn[] = (string) $cid;
            }
        }

        sort($cidsOn);

        return $cidsOn;
    }

    /**
     * Stores the switch of a flag for one audience. When the state changes it flushes the entity manager, so
     * whatever else is pending in the unit of work is written with it; the same state again writes nothing.
     *
     * In one transaction, the row is reserved with INSERT IGNORE in the opposite state, read with a write lock and
     * switched through the ORM. A first switch is therefore always an update for entity listeners (and an onFlush
     * audit), also to off; nobody outside the transaction sees the reserved state, and a competing first switch waits
     * on the unique key and then updates the committed row.
     *
     * The cached states are cleared once the change is committed: at once, or, inside a transaction of the caller,
     * when that ends with the request, command or message (FeatureFlagCacheInvalidationListener).
     *
     * This service is the writer of FeatureFlagAudience: the locking read refreshes the entity, so an unflushed change
     * the caller made to that same row is lost.
     *
     * @param string $cid       The cid of the flag.
     * @param string $audience  The key of the audience whose switch changes.
     * @param bool   $on        Whether the flag is on for the audience from now on.
     * @param string $updatedBy Who switched it, such as the user identifier; cut to its first 180 characters.
     *
     * @throws UnknownFeatureFlagException When there is no flag with the cid or no configured audience with the key;
     *                                     nothing is written.
     * @throws InvalidArgumentException    When the cache refuses the key.
     * @throws Throwable                   When storing fails; the transaction is rolled back.
     */
    public function switchFlag(string $cid, string $audience, bool $on, string $updatedBy): void
    {
        if (!$this->featureFlagAudienceRegistry->hasAudience($audience)) {
            throw UnknownFeatureFlagException::unknownAudience($audience);
        }

        $flag = $this->featureFlagRepository->findFlagByCid($cid)
            ?? throw UnknownFeatureFlagException::unknownFlag($cid);
        $flagId = (int) $flag->getId();
        $updatedBy = mb_substr($updatedBy, 0, self::MAX_UPDATED_BY_LENGTH);
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->reserveSwitchRow($flagId, $audience, !$on, $updatedBy);
            $changed = $this->applySwitchToLockedRow($flagId, $audience, $on, $updatedBy);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        if ($changed && !$connection->isTransactionActive()) {
            $this->clearCachedStates();
        }
    }

    /**
     * Drops the cached states and the copy in memory, so the next check reads the database.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function clearCachedStates(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->states = null;
    }

    /**
     * Drops only the copy in memory (kernel.reset, between requests and messages); the cache item stays.
     */
    public function reset(): void
    {
        $this->states = null;
    }

    /**
     * Makes sure the row of the flag and audience exists; a new row gets the given (opposite) state, which the
     * caller switches before the commit. An existing row is left alone.
     *
     * @param int    $flagId     The id of the flag.
     * @param string $audience   The key of the audience.
     * @param bool   $reservedOn The state of a new row.
     * @param string $updatedBy  Who switched it.
     */
    private function reserveSwitchRow(int $flagId, string $audience, bool $reservedOn, string $updatedBy): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'INSERT IGNORE INTO feature_flag_audiences (feature_flag_id, audience, is_on, updated_at, updated_by)
                VALUES (:flagId, :audience, :on, :now, :updatedBy)',
            [
                'flagId' => $flagId,
                'audience' => $audience,
                'on' => (int) $reservedOn,
                'now' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
                'updatedBy' => $updatedBy,
            ],
        );
    }

    /**
     * Reads the row with a write lock and switches it when its state differs; inside the transaction of
     * switchFlag(). A row reserved by this call always differs.
     *
     * @param int    $flagId    The id of the flag.
     * @param string $audience  The key of the audience.
     * @param bool   $on        Whether the flag is on for the audience from now on.
     * @param string $updatedBy Who switched it.
     *
     * @return bool Whether the state changed.
     *
     * @throws LogicException When the reserved row is gone.
     */
    private function applySwitchToLockedRow(int $flagId, string $audience, bool $on, string $updatedBy): bool
    {
        $audienceSwitch = $this->featureFlagAudienceRepository->findSwitchOfForUpdate($flagId, $audience)
            ?? throw new LogicException(
                sprintf('No feature_flag_audiences row for flag %d and %s.', $flagId, $audience),
            );

        if ($audienceSwitch->isOn() === $on) {
            return false;
        }

        $audienceSwitch->switchTo($on, $updatedBy);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @param string       $cid       The cid of the flag.
     * @param string[]     $audiences The keys of the audiences whose switches count.
     *
     * @return bool Whether the flag is on for at least one of them.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    private function isOnForOneOf(string $cid, array $audiences): bool
    {
        foreach ($audiences as $audience) {
            if ($this->isOnFor($cid, $audience)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The audiences whose switches count for the caller: a visitor without login gets the guest audience (if one is
     * configured), a signed-in user what the resolver returns, limited to the configured audiences. Without a
     * resolver (no audiences configured) there are none.
     *
     * @return list<string>
     */
    private function findAudiencesOfCaller(): array
    {
        $user = $this->security->getUser();

        if ($user === null) {
            $guestAudience = $this->featureFlagAudienceRegistry->findGuestAudience();

            return $guestAudience === null ? [] : [$guestAudience];
        }

        if ($this->featureFlagAudienceResolver === null) {
            return [];
        }

        $audiences = array_filter(
            $this->featureFlagAudienceResolver->resolveAudiencesOf($user),
            $this->featureFlagAudienceRegistry->hasAudience(...),
        );

        return array_values(array_unique($audiences));
    }

    /**
     * @return array<string, array<string, bool>> Per flag cid, per audience key, whether it is on.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    private function findStates(): array
    {
        return $this->states ??= $this->cache->get(self::CACHE_KEY, $this->readStatesForCache(...));
    }

    /**
     * @param ItemInterface $item The cache item the states are stored in.
     *
     * @return array<string, array<string, bool>>
     */
    private function readStatesForCache(ItemInterface $item): array
    {
        $item->expiresAfter(self::CACHE_LIFETIME_SECONDS);

        return $this->featureFlagAudienceRepository->findAllStates();
    }
}
