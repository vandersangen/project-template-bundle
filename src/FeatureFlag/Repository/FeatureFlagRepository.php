<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlag;

/**
 * PERFORMANCE: the table holds one row per feature flag the project defines, a handful, so reading it whole is
 * fine. Should a project ever hold thousands of flags, page the list.
 *
 * @extends ServiceEntityRepository<FeatureFlag>
 */
class FeatureFlagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureFlag::class);
    }

    /**
     * @param string $cid The key of the flag.
     *
     * @return FeatureFlag|null The flag, or null when the project has no such flag.
     */
    public function findFlagByCid(string $cid): ?FeatureFlag
    {
        return $this->findOneBy(['cid' => $cid]);
    }

    /**
     * @return list<FeatureFlag> Every flag, ordered by cid.
     */
    public function findAllFlags(): array
    {
        $query = $this->createQueryBuilder('flag')
            ->orderBy('flag.cid', 'ASC');

        /** @var list<FeatureFlag> $flags */
        $flags = $query->getQuery()->getResult();

        return $flags;
    }
}
