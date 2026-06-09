<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceTemplate;

/**
 * @extends ServiceEntityRepository<InvoiceTemplate>
 */
class InvoiceTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceTemplate::class);
    }

    public function save(InvoiceTemplate $template, bool $flush = false): void
    {
        $this->getEntityManager()->persist($template);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByOwnerKey(string $ownerKey): ?InvoiceTemplate
    {
        return $this->findOneBy(['ownerKey' => $ownerKey]);
    }
}
