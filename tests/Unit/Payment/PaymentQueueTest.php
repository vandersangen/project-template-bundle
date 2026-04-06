<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Handler\CancelSubscriptionMessageHandler;
use VanDerSangen\ProjectTemplateBundle\Payment\Handler\CreateSubscriptionMessageHandler;
use VanDerSangen\ProjectTemplateBundle\Payment\Handler\SyncPaymentMessageHandler;
use VanDerSangen\ProjectTemplateBundle\Payment\Handler\SyncSubscriptionMessageHandler;
use VanDerSangen\ProjectTemplateBundle\Payment\Message\CancelSubscriptionMessage;
use VanDerSangen\ProjectTemplateBundle\Payment\Message\CreateSubscriptionMessage;
use VanDerSangen\ProjectTemplateBundle\Payment\Message\SyncPaymentMessage;
use VanDerSangen\ProjectTemplateBundle\Payment\Message\SyncSubscriptionMessage;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentQueueTest extends TestCase
{
    // ==================== Messages ====================

    public function testCreateSubscriptionMessageGetters(): void
    {
        $msg = new CreateSubscriptionMessage(
            tenantId: 5,
            userId: 10,
            provider: 'mollie',
            amountCents: 999,
            interval: 'monthly',
            returnUrl: 'https://return.url',
            currency: 'EUR',
            description: 'Premium',
            cancelUrl: 'https://cancel.url',
        );

        $this->assertSame(5, $msg->getTenantId());
        $this->assertSame(10, $msg->getUserId());
        $this->assertSame('mollie', $msg->getProvider());
        $this->assertSame(999, $msg->getAmountCents());
        $this->assertSame('monthly', $msg->getInterval());
        $this->assertSame('https://return.url', $msg->getReturnUrl());
        $this->assertSame('EUR', $msg->getCurrency());
        $this->assertSame('Premium', $msg->getDescription());
        $this->assertSame('https://cancel.url', $msg->getCancelUrl());
    }

    public function testCreateSubscriptionMessageDefaultCurrency(): void
    {
        $msg = new CreateSubscriptionMessage(1, 1, 'mollie', 100, 'monthly', 'http://return');
        $this->assertSame('EUR', $msg->getCurrency());
        $this->assertNull($msg->getDescription());
        $this->assertNull($msg->getCancelUrl());
    }

    public function testSyncSubscriptionMessageGetter(): void
    {
        $msg = new SyncSubscriptionMessage(42);
        $this->assertSame(42, $msg->getSubscriptionId());
    }

    public function testCancelSubscriptionMessageGetters(): void
    {
        $msg = new CancelSubscriptionMessage(7, true, 'user_request', true);
        $this->assertSame(7, $msg->getSubscriptionId());
        $this->assertTrue($msg->isImmediate());
        $this->assertSame('user_request', $msg->getReason());
        $this->assertTrue($msg->isAllowOneMoreCharge());
    }

    public function testCancelSubscriptionMessageDefaults(): void
    {
        $msg = new CancelSubscriptionMessage(3);
        $this->assertFalse($msg->isImmediate());
        $this->assertNull($msg->getReason());
        $this->assertFalse($msg->isAllowOneMoreCharge());
    }

    public function testSyncPaymentMessageGetters(): void
    {
        $msg = new SyncPaymentMessage(55, true);
        $this->assertSame(55, $msg->getPaymentId());
        $this->assertTrue($msg->isForceSync());
    }

    public function testSyncPaymentMessageDefaultForceSync(): void
    {
        $msg = new SyncPaymentMessage(1);
        $this->assertFalse($msg->isForceSync());
    }

    // ==================== Handlers ====================

    public function testCreateSubscriptionHandlerDelegatesToService(): void
    {
        $service = $this->createMock(PaymentService::class);
        $service->expects($this->once())
            ->method('createSubscription')
            ->with(
                tenantId: 5,
                userId: 10,
                provider: PaymentProvider::MOLLIE,
                amountCents: 999,
                interval: SubscriptionInterval::MONTHLY,
                returnUrl: 'https://return.url',
                currency: 'EUR',
                description: 'Premium',
                cancelUrl: null,
            );

        $handler = new CreateSubscriptionMessageHandler($service);
        $handler(new CreateSubscriptionMessage(
            5,
            10,
            'mollie',
            999,
            'monthly',
            'https://return.url',
            'EUR',
            'Premium'
        ));
    }

    public function testSyncSubscriptionHandlerDelegatesToService(): void
    {
        $service = $this->createMock(PaymentService::class);
        $repo = $this->createMock(SubscriptionRepository::class);

        $sub = new Subscription();
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY)
            ->setStatus(SubscriptionStatus::ACTIVE);

        $repo->expects($this->once())->method('find')->with(42)->willReturn($sub);
        $service->expects($this->once())->method('syncSubscription')->with($sub);

        $handler = new SyncSubscriptionMessageHandler($service, $repo);
        $handler(new SyncSubscriptionMessage(42));
    }

    public function testSyncSubscriptionHandlerThrowsWhenNotFound(): void
    {
        $service = $this->createMock(PaymentService::class);
        $repo = $this->createMock(SubscriptionRepository::class);
        $repo->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $handler = new SyncSubscriptionMessageHandler($service, $repo);
        $handler(new SyncSubscriptionMessage(999));
    }

    public function testCancelSubscriptionHandlerDelegatesToService(): void
    {
        $service = $this->createMock(PaymentService::class);
        $repo = $this->createMock(SubscriptionRepository::class);

        $sub = new Subscription();
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY)
            ->setStatus(SubscriptionStatus::ACTIVE);

        $repo->expects($this->once())->method('find')->with(7)->willReturn($sub);
        $service->expects($this->once())
            ->method('cancelSubscription')
            ->with($sub, true, 'user_request', false);

        $handler = new CancelSubscriptionMessageHandler($service, $repo);
        $handler(new CancelSubscriptionMessage(7, true, 'user_request', false));
    }

    public function testCancelSubscriptionHandlerThrowsWhenNotFound(): void
    {
        $service = $this->createMock(PaymentService::class);
        $repo = $this->createMock(SubscriptionRepository::class);
        $repo->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $handler = new CancelSubscriptionMessageHandler($service, $repo);
        $handler(new CancelSubscriptionMessage(999));
    }

    public function testSyncPaymentHandlerDelegatesToService(): void
    {
        $service = $this->createMock(PaymentService::class);
        $repo = $this->createMock(PaymentRepository::class);

        $payment = new Payment();
        $payment->setTenantId(1)->setUserId(1)->setProvider(PaymentProvider::MOLLIE)
            ->setAmountCents(100)->setCurrency('EUR')->setStatus(PaymentStatus::PENDING);

        $repo->expects($this->once())->method('find')->with(55)->willReturn($payment);
        $service->expects($this->once())->method('syncPayment')->with($payment, true);

        $handler = new SyncPaymentMessageHandler($service, $repo);
        $handler(new SyncPaymentMessage(55, true));
    }

    public function testSyncPaymentHandlerThrowsWhenNotFound(): void
    {
        $service = $this->createMock(PaymentService::class);
        $repo = $this->createMock(PaymentRepository::class);
        $repo->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $handler = new SyncPaymentMessageHandler($service, $repo);
        $handler(new SyncPaymentMessage(999));
    }
}
