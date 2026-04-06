<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use PHPUnit\Framework\TestCase;

class SubscriptionEntityTest extends TestCase
{
    private function makeSubscription(): Subscription
    {
        $sub = new Subscription();
        $sub->setTenantId(1)
            ->setUserId(10)
            ->setToolUserReference('tenant-1')
            ->setProvider(PaymentProvider::MOLLIE)
            ->setStatus(SubscriptionStatus::ACTIVE)
            ->setAmountCents(999)
            ->setCurrency('EUR')
            ->setInterval(SubscriptionInterval::MONTHLY);
        return $sub;
    }

    public function testDefaultValues(): void
    {
        $sub = new Subscription();
        $this->assertSame(0, $sub->getAmountCents());
        $this->assertSame('EUR', $sub->getCurrency());
        $this->assertSame(0, $sub->getFailedChargeCount());
        $this->assertSame(0, $sub->getChargeCount());
        $this->assertNull($sub->getMaxCharges());
        $this->assertNotNull($sub->getCreatedAt());
    }

    public function testSettersAndGetters(): void
    {
        $sub = $this->makeSubscription();
        $this->assertSame(1, $sub->getTenantId());
        $this->assertSame(10, $sub->getUserId());
        $this->assertSame('tenant-1', $sub->getToolUserReference());
        $this->assertSame(PaymentProvider::MOLLIE, $sub->getProvider());
        $this->assertSame(SubscriptionStatus::ACTIVE, $sub->getStatus());
        $this->assertSame(999, $sub->getAmountCents());
        $this->assertSame('EUR', $sub->getCurrency());
        $this->assertSame(SubscriptionInterval::MONTHLY, $sub->getInterval());
    }

    public function testHasNotReachedMaxChargesWhenNull(): void
    {
        $sub = $this->makeSubscription();
        $sub->setMaxCharges(null);
        $this->assertFalse($sub->hasReachedMaxCharges());
    }

    public function testHasNotReachedMaxChargesWhenBelowLimit(): void
    {
        $sub = $this->makeSubscription();
        $sub->setMaxCharges(3);
        $sub->setChargeCount(2);
        $this->assertFalse($sub->hasReachedMaxCharges());
    }

    public function testHasReachedMaxChargesWhenAtLimit(): void
    {
        $sub = $this->makeSubscription();
        $sub->setMaxCharges(3);
        $sub->setChargeCount(3);
        $this->assertTrue($sub->hasReachedMaxCharges());
    }

    public function testHasReachedMaxChargesWhenExceedsLimit(): void
    {
        $sub = $this->makeSubscription();
        $sub->setMaxCharges(2);
        $sub->setChargeCount(5);
        $this->assertTrue($sub->hasReachedMaxCharges());
    }

    public function testIncrementChargeCount(): void
    {
        $sub = $this->makeSubscription();
        $sub->setChargeCount(2);
        $sub->incrementChargeCount();
        $this->assertSame(3, $sub->getChargeCount());
        $sub->incrementChargeCount();
        $this->assertSame(4, $sub->getChargeCount());
    }

    public function testAllowOneMoreChargePattern(): void
    {
        $sub = $this->makeSubscription();
        $sub->setChargeCount(5);

        // When cancelling with allowOneMoreCharge: set maxCharges = chargeCount + 1
        $sub->setMaxCharges($sub->getChargeCount() + 1);
        $this->assertSame(6, $sub->getMaxCharges());
        $this->assertFalse($sub->hasReachedMaxCharges());

        // After one more charge
        $sub->incrementChargeCount();
        $this->assertTrue($sub->hasReachedMaxCharges());
    }

    public function testToArrayContainsExpectedKeys(): void
    {
        $sub = $this->makeSubscription();
        $array = $sub->toArray();

        $expectedKeys = [
            'id',
            'tenantId',
            'userId',
            'toolUserReference',
            'paymentApiSubscriptionId',
            'provider',
            'status',
            'amountCents',
            'currency',
            'interval',
            'description',
            'checkoutUrl',
            'providerSubscriptionId',
            'providerCustomerId',
            'nextBillingDate',
            'failedChargeCount',
            'maxCharges',
            'chargeCount',
            'endsAt',
            'createdAt',
            'updatedAt',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, "Missing key: $key");
        }
    }

    public function testToArrayValues(): void
    {
        $sub = $this->makeSubscription();
        $sub->setDescription('Premium plan');
        $sub->setMaxCharges(5);
        $sub->setChargeCount(2);

        $array = $sub->toArray();
        $this->assertSame(1, $array['tenantId']);
        $this->assertSame(10, $array['userId']);
        $this->assertSame('tenant-1', $array['toolUserReference']);
        $this->assertSame('mollie', $array['provider']);
        $this->assertSame('active', $array['status']);
        $this->assertSame(999, $array['amountCents']);
        $this->assertSame('EUR', $array['currency']);
        $this->assertSame('monthly', $array['interval']);
        $this->assertSame('Premium plan', $array['description']);
        $this->assertSame(5, $array['maxCharges']);
        $this->assertSame(2, $array['chargeCount']);
    }
}
