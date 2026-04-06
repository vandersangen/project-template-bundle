<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use PHPUnit\Framework\TestCase;

class PaymentEnumsTest extends TestCase
{
    public function testPaymentProviderValues(): void
    {
        $this->assertSame('stripe', PaymentProvider::STRIPE->value);
        $this->assertSame('mollie', PaymentProvider::MOLLIE->value);
    }

    public function testPaymentProviderFromString(): void
    {
        $this->assertSame(PaymentProvider::STRIPE, PaymentProvider::from('stripe'));
        $this->assertSame(PaymentProvider::MOLLIE, PaymentProvider::from('mollie'));
    }

    public function testPaymentProviderFromInvalidStringThrows(): void
    {
        $this->expectException(\ValueError::class);
        PaymentProvider::from('paypal');
    }

    public function testPaymentStatusValues(): void
    {
        $this->assertSame('pending', PaymentStatus::PENDING->value);
        $this->assertSame('paid', PaymentStatus::PAID->value);
        $this->assertSame('failed', PaymentStatus::FAILED->value);
        $this->assertSame('cancelled', PaymentStatus::CANCELLED->value);
        $this->assertSame('expired', PaymentStatus::EXPIRED->value);
        $this->assertSame('refunded', PaymentStatus::REFUNDED->value);
    }

    public function testSubscriptionStatusIsActive(): void
    {
        $this->assertTrue(SubscriptionStatus::ACTIVE->isActive());
        $this->assertFalse(SubscriptionStatus::PENDING->isActive());
        $this->assertFalse(SubscriptionStatus::CANCELLED->isActive());
        $this->assertFalse(SubscriptionStatus::PAST_DUE->isActive());
        $this->assertFalse(SubscriptionStatus::PENDING_CANCELLATION->isActive());
    }

    public function testSubscriptionStatusIsCancellable(): void
    {
        $this->assertTrue(SubscriptionStatus::ACTIVE->isCancellable());
        $this->assertTrue(SubscriptionStatus::PAST_DUE->isCancellable());
        $this->assertTrue(SubscriptionStatus::PENDING_CANCELLATION->isCancellable());
        $this->assertFalse(SubscriptionStatus::CANCELLED->isCancellable());
        $this->assertFalse(SubscriptionStatus::PENDING->isCancellable());
    }

    public function testSubscriptionIntervalValues(): void
    {
        $this->assertSame('monthly', SubscriptionInterval::MONTHLY->value);
        $this->assertSame('quarterly', SubscriptionInterval::QUARTERLY->value);
        $this->assertSame('yearly', SubscriptionInterval::YEARLY->value);
    }

    public function testSubscriptionStatusFromString(): void
    {
        $this->assertSame(SubscriptionStatus::ACTIVE, SubscriptionStatus::from('active'));
        $this->assertSame(SubscriptionStatus::CANCELLED, SubscriptionStatus::from('cancelled'));
        $this->assertSame(SubscriptionStatus::PAST_DUE, SubscriptionStatus::from('past_due'));
        $this->assertSame(SubscriptionStatus::PENDING_CANCELLATION, SubscriptionStatus::from('pending_cancellation'));
    }
}
