<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shopify\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use VanDerSangen\ProjectTemplateBundle\Shopify\Entity\ShopifyConnection;

/**
 * @extends ServiceEntityRepository<ShopifyConnection>
 */
class ShopifyConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopifyConnection::class);
    }

    public function save(ShopifyConnection $connection, bool $flush = false): void
    {
        $this->getEntityManager()->persist($connection);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ShopifyConnection $connection, bool $flush = false): void
    {
        $this->getEntityManager()->remove($connection);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTenantId(int $tenantId): ?ShopifyConnection
    {
        return $this->findOneBy(['tenantId' => $tenantId]);
    }
}
