<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Repository;

use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mail::class);
    }

    public function save(Mail $mail, bool $flush = false): void
    {
        $this->getEntityManager()->persist($mail);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySender(string $sender): array
    {
        return $this->findBy(['sender' => $sender]);
    }

    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }
}
