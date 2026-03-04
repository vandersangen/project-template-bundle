<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\SuperAdmin\Repository;

use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuperAdminUser>
 */
class SuperAdminUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuperAdminUser::class);
    }

    public function findByUsername(string $username): ?SuperAdminUser
    {
        return $this->findOneBy(['username' => $username]);
    }
}
