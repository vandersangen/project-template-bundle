<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Repository;

use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cron>
 */
class CronRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cron::class);
    }

    public function save(Cron $cron, bool $flush = false): void
    {
        $this->getEntityManager()->persist($cron);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Cron $cron, bool $flush = false): void
    {
        $this->getEntityManager()->remove($cron);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Cron[]
     */
    public function findDue(\DateTimeImmutable $time): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.enabled = :enabled')
            ->andWhere('c.nextRunAt IS NULL OR c.nextRunAt <= :at')
            ->setParameter('enabled', true)
            ->setParameter('at', $time)
            ->orderBy('c.nextRunAt', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
