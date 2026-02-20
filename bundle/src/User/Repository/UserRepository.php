<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\User\Repository;

use LarsVanDerSangen\ProjectTemplateBundle\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByResetToken(string $token): ?User
    {
        return $this->findOneBy(['resetToken' => $token]);
    }
}
