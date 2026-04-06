<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tenant\Repository;

use VanDerSangen\ProjectTemplateBundle\Tenant\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function save(Tenant $tenant, bool $flush = false): void
    {
        $this->getEntityManager()->persist($tenant);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tenant $tenant, bool $flush = false): void
    {
        $this->getEntityManager()->remove($tenant);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByOwnerUserId(int $userId): ?Tenant
    {
        return $this->findOneBy(['ownerUserId' => $userId]);
    }
}
