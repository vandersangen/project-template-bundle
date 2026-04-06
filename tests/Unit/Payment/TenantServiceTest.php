<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Tenant\Entity\Tenant;
use VanDerSangen\ProjectTemplateBundle\Tenant\Exception\TenantOwnerProtectionException;
use VanDerSangen\ProjectTemplateBundle\Tenant\Repository\TenantRepository;
use VanDerSangen\ProjectTemplateBundle\Tenant\Service\TenantService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TenantServiceTest extends TestCase
{
    private TenantRepository&MockObject $tenantRepo;
    private SubscriptionRepository&MockObject $subscriptionRepo;
    private TenantService $service;

    protected function setUp(): void
    {
        $this->tenantRepo = $this->createMock(TenantRepository::class);
        $this->subscriptionRepo = $this->createMock(SubscriptionRepository::class);
        $this->service = new TenantService($this->tenantRepo, $this->subscriptionRepo);
    }

    private function makeTenant(int $tenantId = 1, int $ownerUserId = 10): Tenant
    {
        $tenant = new Tenant();
        $tenant->setName('Test Tenant')->setOwnerUserId($ownerUserId);
        $ref = new \ReflectionProperty(Tenant::class, 'id');
        $ref->setValue($tenant, $tenantId);
        return $tenant;
    }

    private function makeActiveSubscription(int $tenantId): Subscription
    {
        $sub = new Subscription();
        $sub->setTenantId($tenantId)->setUserId(10)->setToolUserReference('tenant-' . $tenantId)
            ->setStatus(SubscriptionStatus::ACTIVE)->setProvider(PaymentProvider::MOLLIE)
            ->setAmountCents(999)->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);
        return $sub;
    }

    // ==================== createTenant ====================

    public function testCreateTenantPersistsAndReturns(): void
    {
        $this->tenantRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Tenant::class), true);

        $tenant = $this->service->createTenant('Acme', 42, 'Acme BV', 'NL123', 'billing@acme.com');

        $this->assertSame('Acme', $tenant->getName());
        $this->assertSame(42, $tenant->getOwnerUserId());
        $this->assertSame('Acme BV', $tenant->getCompanyName());
        $this->assertSame('NL123', $tenant->getVatNumber());
        $this->assertSame('billing@acme.com', $tenant->getBillingEmail());
    }

    public function testCreateTenantWithMinimalData(): void
    {
        $this->tenantRepo->expects($this->once())->method('save');
        $tenant = $this->service->createTenant('My Tenant', 1);
        $this->assertSame('My Tenant', $tenant->getName());
        $this->assertNull($tenant->getCompanyName());
    }

    // ==================== assertUserIsDeletable ====================

    public function testAssertUserIsDeletableDoesNotThrowForNonOwner(): void
    {
        $tenant = $this->makeTenant(1, 10);
        $this->tenantRepo->method('find')->with(1)->willReturn($tenant);

        // user 99 is NOT the owner → should never check subscriptions
        $this->subscriptionRepo->expects($this->never())->method('findActiveByTenantId');

        $this->service->assertUserIsDeletable(99, 1);
    }

    public function testAssertUserIsDeletableDoesNotThrowWhenTenantNotFound(): void
    {
        $this->tenantRepo->method('find')->willReturn(null);
        $this->subscriptionRepo->expects($this->never())->method('findActiveByTenantId');

        $this->service->assertUserIsDeletable(10, 999);
    }

    public function testAssertUserIsDeletableDoesNotThrowWhenOwnerButNoActiveSubscription(): void
    {
        $tenant = $this->makeTenant(1, 10);
        $this->tenantRepo->method('find')->with(1)->willReturn($tenant);
        $this->subscriptionRepo->method('findActiveByTenantId')->with(1)->willReturn(null);
        $this->subscriptionRepo->method('findOneBy')->willReturn(null);

        // Should not throw
        $this->service->assertUserIsDeletable(10, 1);
        $this->assertTrue(true);
    }

    public function testAssertUserIsDeletableThrowsWhenOwnerHasActiveSubscription(): void
    {
        $tenant = $this->makeTenant(1, 10);
        $activeSub = $this->makeActiveSubscription(1);

        $this->tenantRepo->method('find')->willReturn($tenant);
        $this->subscriptionRepo->method('findActiveByTenantId')->with(1)->willReturn($activeSub);

        $this->expectException(TenantOwnerProtectionException::class);
        $this->service->assertUserIsDeletable(10, 1);
    }

    public function testAssertUserIsDeletableThrowsWhenOwnerHasPendingCancellationSubscription(): void
    {
        $tenant = $this->makeTenant(1, 10);

        $pendingSub = new Subscription();
        $pendingSub->setTenantId(1)->setUserId(10)->setToolUserReference('tenant-1')
            ->setStatus(SubscriptionStatus::PENDING_CANCELLATION)->setProvider(PaymentProvider::MOLLIE)
            ->setAmountCents(999)->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->tenantRepo->method('find')->willReturn($tenant);
        $this->subscriptionRepo->method('findActiveByTenantId')->with(1)->willReturn(null);
        $this->subscriptionRepo->method('findOneBy')
            ->with(['tenantId' => 1, 'status' => SubscriptionStatus::PENDING_CANCELLATION->value])
            ->willReturn($pendingSub);

        $this->expectException(TenantOwnerProtectionException::class);
        $this->service->assertUserIsDeletable(10, 1);
    }

    // ==================== isOwner ====================

    public function testIsOwnerReturnsTrueForOwner(): void
    {
        $tenant = $this->makeTenant(1, 10);
        $this->tenantRepo->method('find')->willReturn($tenant);
        $this->assertTrue($this->service->isOwner(10, 1));
    }

    public function testIsOwnerReturnsFalseForNonOwner(): void
    {
        $tenant = $this->makeTenant(1, 10);
        $this->tenantRepo->method('find')->willReturn($tenant);
        $this->assertFalse($this->service->isOwner(99, 1));
    }

    public function testIsOwnerReturnsFalseWhenTenantNotFound(): void
    {
        $this->tenantRepo->method('find')->willReturn(null);
        $this->assertFalse($this->service->isOwner(10, 999));
    }

    // ==================== updateBillingInfo ====================

    public function testUpdateBillingInfoUpdatesFields(): void
    {
        $tenant = $this->makeTenant(1, 10);
        $this->tenantRepo->expects($this->once())->method('save')->with($tenant, true);

        $result = $this->service->updateBillingInfo(
            tenant: $tenant,
            companyName: 'New BV',
            vatNumber: 'NL999',
            billingEmail: 'new@billing.com',
            billingCity: 'Rotterdam',
            billingCountry: 'NL',
        );

        $this->assertSame('New BV', $result->getCompanyName());
        $this->assertSame('NL999', $result->getVatNumber());
        $this->assertSame('new@billing.com', $result->getBillingEmail());
        $this->assertSame('Rotterdam', $result->getBillingCity());
        $this->assertSame('NL', $result->getBillingCountry());
        $this->assertNotNull($result->getUpdatedAt());
    }

    public function testUpdateBillingInfoSkipsNullValues(): void
    {
        $tenant = $this->makeTenant(1, 10);
        $tenant->setCompanyName('Original BV')->setBillingCountry('BE');
        $this->tenantRepo->method('save');

        $this->service->updateBillingInfo($tenant, null, null, null, null, null, null, null, null);

        $this->assertSame('Original BV', $tenant->getCompanyName());
        $this->assertSame('BE', $tenant->getBillingCountry());
    }
}
