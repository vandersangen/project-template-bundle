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

    /**
     * @return Subscription[]
     */
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

    /**
     * Finds all subscriptions in a non-terminal state (excludes CANCELLED).
     * Used by the daily full sync cron to reconcile all live subscriptions with Mollie.
     *
     * @return Subscription[]
     */
    public function findAllSyncable(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status IN (:statuses)')
            ->setParameter('statuses', [
                SubscriptionStatus::PENDING->value,
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::PAST_DUE->value,
                SubscriptionStatus::VERIFICATION_FAILED->value,
                SubscriptionStatus::PENDING_CANCELLATION->value,
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds active subscriptions whose nextBillingDate is in the past.
     * Used by the sync cron to catch up on missed webhooks.
     *
     * @return Subscription[]
     */
    public function findOverdueForSync(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :active')
            ->andWhere('s.nextBillingDate IS NOT NULL')
            ->andWhere('s.nextBillingDate < :now')
            ->setParameter('active', SubscriptionStatus::ACTIVE->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
