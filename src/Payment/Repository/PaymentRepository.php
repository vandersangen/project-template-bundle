<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Repository;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $payment, bool $flush = false): void
    {
        $this->getEntityManager()->persist($payment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTenantId(int $tenantId): array
    {
        return $this->findBy(['tenantId' => $tenantId], ['createdAt' => 'DESC']);
    }

    public function findByPaymentApiId(int $paymentApiPaymentId): ?Payment
    {
        return $this->findOneBy(['paymentApiPaymentId' => $paymentApiPaymentId]);
    }

    public function findBySubscriptionId(int $subscriptionId): array
    {
        return $this->findBy(['subscription' => $subscriptionId], ['createdAt' => 'DESC']);
    }
}
