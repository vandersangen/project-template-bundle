<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceNumberSequence;

/**
 * @extends ServiceEntityRepository<InvoiceNumberSequence>
 */
class InvoiceNumberSequenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceNumberSequence::class);
    }

    public function findOneByOwnerKeyAndYear(string $ownerKey, int $year): ?InvoiceNumberSequence
    {
        return $this->findOneBy(['ownerKey' => $ownerKey, 'year' => $year]);
    }
}
