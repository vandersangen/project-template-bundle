<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagAudience;

/**
 * PERFORMANCE: the table holds at most the flags times the audiences rows, so reading it whole is fine. Should a
 * project get a very large number of flags, read the states per flag instead.
 *
 * @extends ServiceEntityRepository<FeatureFlagAudience>
 */
class FeatureFlagAudienceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureFlagAudience::class);
    }

    /**
     * Reads the row with a lock, refreshed from the database. Call it inside a transaction.
     *
     * @param int    $featureFlagId The id of the flag.
     * @param string $audience      The key of the audience.
     *
     * @return FeatureFlagAudience|null The row, or null when the flag was never switched for the audience.
     */
    public function findSwitchOfForUpdate(int $featureFlagId, string $audience): ?FeatureFlagAudience
    {
        $query = $this->createQueryBuilder('audienceSwitch')
            ->andWhere('IDENTITY(audienceSwitch.featureFlag) = :featureFlagId')
            ->andWhere('audienceSwitch.audience = :audience');

        $query->setParameter('featureFlagId', $featureFlagId);
        $query->setParameter('audience', $audience);

        $doctrineQuery = $query->getQuery();
        $doctrineQuery->setLockMode(LockMode::PESSIMISTIC_WRITE);
        $doctrineQuery->setHint(Query::HINT_REFRESH, true);

        /** @var FeatureFlagAudience|null $audienceSwitch */
        $audienceSwitch = $doctrineQuery->getOneOrNullResult();

        return $audienceSwitch;
    }

    /**
     * @return array<string, array<string, bool>> Per flag cid, per audience key, whether it is on.
     */
    public function findAllStates(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT flag.cid, audience_switch.audience, audience_switch.is_on
            FROM feature_flag_audiences audience_switch
            INNER JOIN feature_flags flag ON flag.id = audience_switch.feature_flag_id'
        );

        $states = [];

        foreach ($rows as $row) {
            $states[(string) $row['cid']][(string) $row['audience']] = (bool) $row['is_on'];
        }

        return $states;
    }

    /**
     * @return list<FeatureFlagAudience> Every row, with its flag loaded in the same query.
     */
    public function findAllSwitches(): array
    {
        $query = $this->createQueryBuilder('audienceSwitch')
            ->addSelect('flag')
            ->innerJoin('audienceSwitch.featureFlag', 'flag')
            ->orderBy('flag.cid', 'ASC')
            ->addOrderBy('audienceSwitch.audience', 'ASC');

        /** @var list<FeatureFlagAudience> $switches */
        $switches = $query->getQuery()->getResult();

        return $switches;
    }
}
