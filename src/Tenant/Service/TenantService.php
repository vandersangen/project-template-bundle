<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tenant\Service;

use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Tenant\Entity\Tenant;
use VanDerSangen\ProjectTemplateBundle\Tenant\Exception\TenantOwnerProtectionException;
use VanDerSangen\ProjectTemplateBundle\Tenant\Repository\TenantRepository;
use DateTimeImmutable;

class TenantService
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function createTenant(
        string $name,
        int $ownerUserId,
        ?string $companyName = null,
        ?string $vatNumber = null,
        ?string $billingEmail = null,
    ): Tenant {
        $tenant = new Tenant();
        $tenant->setName($name)
            ->setOwnerUserId($ownerUserId)
            ->setCompanyName($companyName)
            ->setVatNumber($vatNumber)
            ->setBillingEmail($billingEmail);

        $this->tenantRepository->save($tenant, true);

        return $tenant;
    }

    /**
     * Guard: throws when trying to delete or deactivate the tenant owner
     * while an active subscription exists.
     *
     * @throws \VanDerSangen\ProjectTemplateBundle\Tenant\Exception\TenantOwnerProtectionException
     */
    public function assertUserIsDeletable(int $userId, int $tenantId): void
    {
        $tenant = $this->tenantRepository->find($tenantId);
        if ($tenant === null || $tenant->getOwnerUserId() !== $userId) {
            return;
        }

        $activeSubscription = $this->subscriptionRepository->findActiveByTenantId($tenantId);
        if ($activeSubscription !== null) {
            throw TenantOwnerProtectionException::cannotDeleteOwner($userId, $tenantId);
        }

        // Also block when pending cancellation (still has a final charge coming)
        $pendingSubscription = $this->subscriptionRepository->findOneBy([
            'tenantId' => $tenantId,
            'status' => SubscriptionStatus::PENDING_CANCELLATION->value,
        ]);
        if ($pendingSubscription !== null) {
            throw TenantOwnerProtectionException::cannotDeleteOwner($userId, $tenantId);
        }
    }

    /**
     * Returns true when the given user is the owner of the given tenant.
     */
    public function isOwner(int $userId, int $tenantId): bool
    {
        $tenant = $this->tenantRepository->find($tenantId);
        return $tenant !== null && $tenant->getOwnerUserId() === $userId;
    }

    public function updateBillingInfo(
        Tenant $tenant,
        ?string $companyName = null,
        ?string $vatNumber = null,
        ?string $billingEmail = null,
        ?string $billingAddressLine1 = null,
        ?string $billingAddressLine2 = null,
        ?string $billingCity = null,
        ?string $billingPostalCode = null,
        ?string $billingCountry = null,
    ): Tenant {
        if ($companyName !== null) {
            $tenant->setCompanyName($companyName);
        }
        if ($vatNumber !== null) {
            $tenant->setVatNumber($vatNumber);
        }
        if ($billingEmail !== null) {
            $tenant->setBillingEmail($billingEmail);
        }
        if ($billingAddressLine1 !== null) {
            $tenant->setBillingAddressLine1($billingAddressLine1);
        }
        if ($billingAddressLine2 !== null) {
            $tenant->setBillingAddressLine2($billingAddressLine2);
        }
        if ($billingCity !== null) {
            $tenant->setBillingCity($billingCity);
        }
        if ($billingPostalCode !== null) {
            $tenant->setBillingPostalCode($billingPostalCode);
        }
        if ($billingCountry !== null) {
            $tenant->setBillingCountry($billingCountry);
        }

        $tenant->setUpdatedAt(new DateTimeImmutable());
        $this->tenantRepository->save($tenant, true);

        return $tenant;
    }
}
