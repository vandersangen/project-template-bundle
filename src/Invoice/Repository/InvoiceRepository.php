<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\Invoice\Entity\Invoice;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $invoice, bool $flush = false): void
    {
        $this->getEntityManager()->persist($invoice);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneBySource(string $ownerKey, string $sourceType, string $sourceId): ?Invoice
    {
        return $this->findOneBy([
            'ownerKey' => $ownerKey,
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
        ]);
    }

    /**
     * @return list<Invoice>
     */
    public function findByOwnerKey(?string $ownerKey = null, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($ownerKey !== null) {
            $qb->andWhere('i.ownerKey = :ownerKey')->setParameter('ownerKey', $ownerKey);
        }

        /** @var list<Invoice> $result */
        $result = $qb->getQuery()->getResult();
        return $result;
    }
}
