<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\InvoiceItem;

/**
 * @extends ServiceEntityRepository<InvoiceItem>
 */
class InvoiceItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceItem::class);
    }
}
