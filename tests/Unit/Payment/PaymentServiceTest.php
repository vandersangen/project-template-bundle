<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Client\PaymentApiClient;
use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment;
use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Event\PaymentCreatedEvent;
use VanDerSangen\ProjectTemplateBundle\Payment\Event\PaymentStatusChangedEvent;
use VanDerSangen\ProjectTemplateBundle\Payment\Event\SubscriptionCancelledEvent;
use VanDerSangen\ProjectTemplateBundle\Payment\Event\SubscriptionCreatedEvent;
use VanDerSangen\ProjectTemplateBundle\Payment\Event\SubscriptionStatusChangedEvent;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentServiceTest extends TestCase
{
    private PaymentApiClient&MockObject $apiClient;
    private SubscriptionRepository&MockObject $subscriptionRepo;
    private PaymentRepository&MockObject $paymentRepo;
    private EventDispatcherInterface&MockObject $dispatcher;
    private PaymentService $service;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(PaymentApiClient::class);
        $this->subscriptionRepo = $this->createMock(SubscriptionRepository::class);
        $this->paymentRepo = $this->createMock(PaymentRepository::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->service = new PaymentService(
            $this->apiClient,
            $this->subscriptionRepo,
            $this->paymentRepo,
            $this->dispatcher,
        );
    }

    // ==================== createSubscription ====================

    public function testCreateSubscriptionCallsApiAndPersists(): void
    {
        $this->apiClient->expects($this->once())
            ->method('createSubscription')
            ->with('mollie', 'tenant-5', 999, 'https://return.url', 'monthly', 'EUR', 'Premium', null)
            ->willReturn([
                'id' => 42,
                'status' => 'active',
                'checkoutUrl' => 'https://checkout.mollie.com/sub',
                'nextBillingDate' => '2026-04-14T00:00:00+00:00',
            ]);

        $this->subscriptionRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Subscription::class), true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SubscriptionCreatedEvent::class));

        $sub = $this->service->createSubscription(
            tenantId: 5,
            userId: 10,
            provider: PaymentProvider::MOLLIE,
            amountCents: 999,
            interval: SubscriptionInterval::MONTHLY,
            returnUrl: 'https://return.url',
            currency: 'EUR',
            description: 'Premium',
        );

        $this->assertSame(42, $sub->getPaymentApiSubscriptionId());
        $this->assertSame(5, $sub->getTenantId());
        $this->assertSame(10, $sub->getUserId());
        $this->assertSame('tenant-5', $sub->getToolUserReference());
        $this->assertSame('https://checkout.mollie.com/sub', $sub->getCheckoutUrl());
        $this->assertSame(SubscriptionStatus::PENDING, $sub->getStatus());
        $this->assertNotNull($sub->getNextBillingDate());
    }

    public function testCreateSubscriptionUsesToolUserReferenceFromTenantId(): void
    {
        $this->apiClient->expects($this->once())
            ->method('createSubscription')
            ->with($this->anything(), 'tenant-99', $this->anything(), $this->anything())
            ->willReturn(['id' => 1, 'status' => 'pending', 'checkoutUrl' => 'http://url']);

        $this->subscriptionRepo->method('save');
        $this->dispatcher->method('dispatch');

        $sub = $this->service->createSubscription(
            tenantId: 99,
            userId: 1,
            provider: PaymentProvider::STRIPE,
            amountCents: 500,
            interval: SubscriptionInterval::YEARLY,
            returnUrl: 'http://return',
        );

        $this->assertSame('tenant-99', $sub->getToolUserReference());
    }

    // ==================== syncSubscription ====================

    public function testSyncSubscriptionUpdatesStatusAndDispatchesEvent(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::PENDING);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->apiClient->expects($this->once())
            ->method('getSubscription')
            ->with(42)
            ->willReturn([
                'status' => 'active',
                'failedChargeCount' => 0,
                'providerSubscriptionId' => 'sub_abc',
                'providerCustomerId' => 'cus_xyz',
                'nextBillingDate' => '2026-04-14T00:00:00+00:00',
            ]);

        $this->subscriptionRepo->expects($this->once())->method('save');
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SubscriptionStatusChangedEvent::class));

        $result = $this->service->syncSubscription($sub);

        $this->assertSame(SubscriptionStatus::ACTIVE, $result->getStatus());
        $this->assertSame('sub_abc', $result->getProviderSubscriptionId());
        $this->assertSame('cus_xyz', $result->getProviderCustomerId());
    }

    public function testSyncSubscriptionDoesNotDispatchEventWhenStatusUnchanged(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::ACTIVE);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->apiClient->method('getSubscription')->willReturn([
            'status' => 'active',
            'failedChargeCount' => 0,
        ]);
        $this->subscriptionRepo->method('save');
        $this->dispatcher->expects($this->never())->method('dispatch');

        $this->service->syncSubscription($sub);
    }

    public function testSyncSubscriptionSkipsWhenNoApiId(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(null);
        $this->apiClient->expects($this->never())->method('getSubscription');
        $this->service->syncSubscription($sub);
    }

    // ==================== cancelSubscription ====================

    public function testCancelSubscriptionImmediately(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::ACTIVE);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->apiClient->expects($this->once())
            ->method('cancelSubscription')
            ->with(42, true, 'user_request')
            ->willReturn(['id' => 42, 'status' => 'cancelled', 'endsAt' => null]);

        $this->subscriptionRepo->expects($this->once())->method('save');

        $dispatchedEvents = [];
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $result = $this->service->cancelSubscription($sub, true, 'user_request');

        $this->assertSame(SubscriptionStatus::CANCELLED, $result->getStatus());
        $eventClasses = array_map(fn($e) => $e::class, $dispatchedEvents);
        $this->assertContains(SubscriptionCancelledEvent::class, $eventClasses);
        $this->assertContains(SubscriptionStatusChangedEvent::class, $eventClasses);
    }

    public function testCancelSubscriptionWithAllowOneMoreCharge(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::ACTIVE);
        $sub->setChargeCount(3);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->apiClient->method('cancelSubscription')
            ->willReturn(['id' => 42, 'status' => 'pending_cancellation', 'endsAt' => '2026-04-14T00:00:00+00:00']);
        $this->subscriptionRepo->method('save');
        $this->dispatcher->method('dispatch');

        $result = $this->service->cancelSubscription($sub, false, null, true);

        // maxCharges should be set to chargeCount + 1 = 4
        $this->assertSame(4, $result->getMaxCharges());
    }

    public function testCancelSubscriptionThrowsWhenNotCancellable(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::CANCELLED);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->expectException(\LogicException::class);
        $this->service->cancelSubscription($sub);
    }

    // ==================== createPayment ====================

    public function testCreatePaymentCallsApiAndPersists(): void
    {
        $this->apiClient->expects($this->once())
            ->method('createPayment')
            ->willReturn([
                'id' => 99,
                'status' => 'pending',
                'checkoutUrl' => 'https://checkout.url',
            ]);

        $this->paymentRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Payment::class), true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PaymentCreatedEvent::class));

        $payment = $this->service->createPayment(
            tenantId: 1,
            userId: 10,
            provider: PaymentProvider::STRIPE,
            amountCents: 2500,
            returnUrl: 'https://return.url',
        );

        $this->assertSame(99, $payment->getPaymentApiPaymentId());
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
        $this->assertSame('https://checkout.url', $payment->getCheckoutUrl());
    }

    // ==================== syncPayment ====================

    public function testSyncPaymentUpdatesStatusAndDispatchesEvent(): void
    {
        $payment = new Payment();
        $payment->setPaymentApiPaymentId(99);
        $payment->setStatus(PaymentStatus::PENDING);
        $payment->setTenantId(1)->setUserId(1)
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)->setCurrency('EUR');

        $this->apiClient->expects($this->once())
            ->method('getPayment')
            ->with(99, false)
            ->willReturn(['status' => 'paid', 'providerPaymentId' => 'tr_abc']);

        $this->paymentRepo->expects($this->once())->method('save');
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PaymentStatusChangedEvent::class));

        $result = $this->service->syncPayment($payment);
        $this->assertSame(PaymentStatus::PAID, $result->getStatus());
        $this->assertSame('tr_abc', $result->getProviderPaymentId());
    }

    public function testSyncPaymentIncreasesChargeCountOnPaidSubscriptionCharge(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::ACTIVE);
        $sub->setChargeCount(2);
        $sub->setMaxCharges(null);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(999)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $payment = new Payment();
        $payment->setPaymentApiPaymentId(55);
        $payment->setStatus(PaymentStatus::PENDING);
        $payment->setSubscription($sub);
        $payment->setTenantId(1)->setUserId(1)
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(999)->setCurrency('EUR');

        $this->apiClient->method('getPayment')->willReturn(['status' => 'paid', 'providerPaymentId' => 'tr_x']);
        $this->apiClient->method('getSubscription')->willReturn(['status' => 'active', 'failedChargeCount' => 0]);
        $this->paymentRepo->method('save');
        $this->subscriptionRepo->method('save');
        $this->dispatcher->method('dispatch');

        $this->service->syncPayment($payment);
        $this->assertSame(3, $sub->getChargeCount());
    }

    public function testSyncPaymentAutoCanelsWhenMaxChargesReached(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::PENDING_CANCELLATION);
        $sub->setChargeCount(2);
        $sub->setMaxCharges(3);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(999)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $payment = new Payment();
        $payment->setPaymentApiPaymentId(55);
        $payment->setStatus(PaymentStatus::PENDING);
        $payment->setSubscription($sub);
        $payment->setTenantId(1)->setUserId(1)
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(999)->setCurrency('EUR');

        $this->apiClient->method('getPayment')->willReturn(['status' => 'paid', 'providerPaymentId' => 'tr_x']);
        $this->apiClient->expects($this->once())
            ->method('cancelSubscription')
            ->with(42, true, 'max_charges_reached')
            ->willReturn(['id' => 42, 'status' => 'cancelled', 'endsAt' => null]);

        $this->paymentRepo->method('save');
        $this->subscriptionRepo->method('save');
        $this->dispatcher->method('dispatch');

        $this->service->syncPayment($payment);
        $this->assertSame(3, $sub->getChargeCount());
    }

    // ==================== retrySubscriptionPayment ====================

    public function testRetrySubscriptionPaymentCreatesPaymentAndDispatchesEvent(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(42);
        $sub->setStatus(SubscriptionStatus::PAST_DUE);
        $sub->setTenantId(1)->setUserId(10)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(999)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->apiClient->expects($this->once())
            ->method('retrySubscriptionPayment')
            ->with(42, 'https://return.url', null)
            ->willReturn(['id' => 77, 'status' => 'pending', 'checkoutUrl' => 'https://checkout.retry.url']);

        $this->paymentRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Payment::class), true);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PaymentCreatedEvent::class));

        $payment = $this->service->retrySubscriptionPayment($sub, 'https://return.url');

        $this->assertSame(77, $payment->getPaymentApiPaymentId());
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
        $this->assertSame('https://checkout.retry.url', $payment->getCheckoutUrl());
        $this->assertSame(1, $payment->getTenantId());
        $this->assertSame(10, $payment->getUserId());
    }

    public function testRetrySubscriptionPaymentPassesCancelUrl(): void
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId(55);
        $sub->setStatus(SubscriptionStatus::PAST_DUE);
        $sub->setTenantId(1)->setUserId(1)->setToolUserReference('t-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(500)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);

        $this->apiClient->expects($this->once())
            ->method('retrySubscriptionPayment')
            ->with(55, 'https://return.url', 'https://cancel.url')
            ->willReturn(['id' => 88, 'status' => 'pending', 'checkoutUrl' => 'https://checkout.retry.url']);

        $this->paymentRepo->method('save');
        $this->dispatcher->method('dispatch');

        $this->service->retrySubscriptionPayment($sub, 'https://return.url', 'https://cancel.url');
    }

    // ==================== isSubscriptionActive ====================

    public function testIsSubscriptionActiveReturnsTrueForActiveStatus(): void
    {
        $sub = new Subscription();
        $sub->setStatus(SubscriptionStatus::ACTIVE);
        $sub->setMaxCharges(null);
        $this->assertTrue($this->service->isSubscriptionActive($sub));
    }

    public function testIsSubscriptionActiveReturnsFalseForCancelledStatus(): void
    {
        $sub = new Subscription();
        $sub->setStatus(SubscriptionStatus::CANCELLED);
        $this->assertFalse($this->service->isSubscriptionActive($sub));
    }

    public function testIsSubscriptionActiveReturnsFalseWhenMaxChargesReached(): void
    {
        $sub = new Subscription();
        $sub->setStatus(SubscriptionStatus::ACTIVE);
        $sub->setMaxCharges(3);
        $sub->setChargeCount(3);
        $this->assertFalse($this->service->isSubscriptionActive($sub));
    }

    public function testIsSubscriptionActiveReturnsFalseForPastDue(): void
    {
        $sub = new Subscription();
        $sub->setStatus(SubscriptionStatus::PAST_DUE);
        $this->assertFalse($this->service->isSubscriptionActive($sub));
    }
}
