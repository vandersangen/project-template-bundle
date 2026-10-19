<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagSetting;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagRegistry;

/**
 * The table holds one row per flag and audience that was ever switched, so at most the flags times the audiences:
 * reading it whole is fine.
 *
 * @extends ServiceEntityRepository<FeatureFlagSetting>
 */
class FeatureFlagSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly FeatureFlagRegistry $featureFlagRegistry)
    {
        parent::__construct($registry, FeatureFlagSetting::class);
    }

    /**
     * @param string $flagKey     The key of the flag.
     * @param string $audienceKey The key of the audience.
     *
     * @return FeatureFlagSetting|null The stored setting, or null when the flag was never switched for the audience.
     */
    public function findSettingOf(string $flagKey, string $audienceKey): ?FeatureFlagSetting
    {
        return $this->findOneBy(['featureFlagKey' => $flagKey, 'audienceKey' => $audienceKey]);
    }

    /**
     * The stored state of every row, by flag key and then audience key.
     *
     * A row of a flag or audience the project no longer has is in it too; it is never asked for, so it counts for
     * nothing.
     *
     * @return array<string, array<string, bool>>
     */
    public function findStoredStates(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT feature_flag, audience, is_on FROM feature_flags',
        );
        $states = [];

        foreach ($rows as $row) {
            $states[(string) $row['feature_flag']][(string) $row['audience']] = (bool) $row['is_on'];
        }

        return $states;
    }

    /**
     * Every stored setting of a flag and an audience the project still has, in one query.
     *
     * A row of a removed flag or audience is left out. Without configured flags or audiences nothing is asked.
     *
     * @return list<FeatureFlagSetting>
     */
    public function findAllSettings(): array
    {
        $knownFlagKeys = array_map(
            static fn (FeatureFlagInterface $flag): string => $flag->key(),
            $this->featureFlagRegistry->flags(),
        );
        $knownAudienceKeys = array_map(
            static fn (FeatureFlagAudienceInterface $audience): string => $audience->key(),
            $this->featureFlagRegistry->audiences(),
        );

        if ($knownFlagKeys === [] || $knownAudienceKeys === []) {
            return [];
        }

        $query = $this->createQueryBuilder('setting')
            ->andWhere('setting.featureFlagKey IN (:knownFlagKeys)')
            ->andWhere('setting.audienceKey IN (:knownAudienceKeys)')
            ->orderBy('setting.id', 'ASC');

        $query->setParameter('knownFlagKeys', $knownFlagKeys);
        $query->setParameter('knownAudienceKeys', $knownAudienceKeys);

        /** @var list<FeatureFlagSetting> $settings */
        $settings = $query->getQuery()->getResult();

        return $settings;
    }
}
