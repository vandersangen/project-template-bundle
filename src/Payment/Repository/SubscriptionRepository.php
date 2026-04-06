<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Repository;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $subscription, bool $flush = false): void
    {
        $this->getEntityManager()->persist($subscription);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTenantId(int $tenantId): array
    {
        return $this->findBy(['tenantId' => $tenantId]);
    }

    public function findActiveByTenantId(int $tenantId): ?Subscription
    {
        return $this->findOneBy([
            'tenantId' => $tenantId,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    public function findByPaymentApiId(int $paymentApiSubscriptionId): ?Subscription
    {
        return $this->findOneBy(['paymentApiSubscriptionId' => $paymentApiSubscriptionId]);
    }

    public function findByToolUserReference(string $toolUserReference): ?Subscription
    {
        return $this->findOneBy(['toolUserReference' => $toolUserReference]);
    }
}
