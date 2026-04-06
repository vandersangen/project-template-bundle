<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment;
use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Event\WebhookReceivedEvent;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\WebhookHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class WebhookHandlerTest extends TestCase
{
    private PaymentService&MockObject $paymentService;
    private SubscriptionRepository&MockObject $subscriptionRepo;
    private PaymentRepository&MockObject $paymentRepo;
    private EventDispatcherInterface&MockObject $dispatcher;
    private WebhookHandler $handler;

    protected function setUp(): void
    {
        $this->paymentService = $this->createMock(PaymentService::class);
        $this->subscriptionRepo = $this->createMock(SubscriptionRepository::class);
        $this->paymentRepo = $this->createMock(PaymentRepository::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new WebhookHandler(
            $this->paymentService,
            $this->subscriptionRepo,
            $this->paymentRepo,
            $this->dispatcher,
            new NullLogger(),
        );
    }

    private function makeSubscription(int $apiId = 42): Subscription
    {
        $sub = new Subscription();
        $sub->setPaymentApiSubscriptionId($apiId)
            ->setStatus(SubscriptionStatus::ACTIVE)
            ->setTenantId(1)->setUserId(10)->setToolUserReference('tenant-1')
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(999)
            ->setCurrency('EUR')->setInterval(SubscriptionInterval::MONTHLY);
        return $sub;
    }

    public function testHandleAlwaysDispatchesRawWebhookEvent(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(WebhookReceivedEvent::class));

        $this->handler->handle(['type' => 'unknown.event', 'data' => []]);
    }

    public function testHandleSubscriptionUpdatedSyncsSubscription(): void
    {
        $sub = $this->makeSubscription(42);

        $this->subscriptionRepo->expects($this->once())
            ->method('findByPaymentApiId')->with(42)->willReturn($sub);

        $this->paymentService->expects($this->once())
            ->method('syncSubscription')->with($sub);

        $this->dispatcher->method('dispatch');

        $this->handler->handle([
            'type' => 'subscription.updated',
            'subscriptionId' => 42,
        ]);
    }

    public function testHandleSubscriptionPaymentSucceededCreatesLocalPayment(): void
    {
        $sub = $this->makeSubscription(42);

        $this->subscriptionRepo->expects($this->once())
            ->method('findByPaymentApiId')->with(42)->willReturn($sub);

        $this->paymentRepo->expects($this->once())
            ->method('findByPaymentApiId')->with(123)->willReturn(null);

        $this->paymentRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(fn(Payment $payment) => $payment->getPaymentApiPaymentId() === 123
                && $payment->getStatus() === PaymentStatus::PENDING
                && $payment->getTenantId() === 1
                && $payment->getUserId() === 10), true);

        $this->paymentService->expects($this->once())
            ->method('syncPayment');

        $this->paymentService->expects($this->once())
            ->method('syncSubscription')->with($sub);

        $this->dispatcher->method('dispatch');

        $this->handler->handle([
            'type' => 'subscription.payment.succeeded',
            'subscriptionId' => 42,
            'paymentId' => 123,
            'data' => [
                'amountCents' => 999,
                'currency' => 'EUR',
                'providerPaymentId' => 'tr_abc',
            ],
        ]);
    }

    public function testHandleSubscriptionPaymentSucceededSkipsIfPaymentAlreadyExists(): void
    {
        $sub = $this->makeSubscription(42);
        $existingPayment = new Payment();
        $existingPayment->setPaymentApiPaymentId(123)->setTenantId(1)->setUserId(1)
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(999)->setCurrency('EUR')
            ->setStatus(PaymentStatus::PAID);

        $this->subscriptionRepo->method('findByPaymentApiId')->willReturn($sub);
        $this->paymentRepo->expects($this->once())
            ->method('findByPaymentApiId')->with(123)->willReturn($existingPayment);

        $this->paymentRepo->expects($this->never())->method('save');
        $this->paymentService->expects($this->once())->method('syncSubscription');
        $this->dispatcher->method('dispatch');

        $this->handler->handle([
            'type' => 'subscription.payment.succeeded',
            'subscriptionId' => 42,
            'paymentId' => 123,
        ]);
    }

    public function testHandleSubscriptionEventWithUnknownApiIdIsIgnored(): void
    {
        $this->subscriptionRepo->expects($this->once())
            ->method('findByPaymentApiId')->with(99)->willReturn(null);

        $this->paymentService->expects($this->never())->method('syncSubscription');
        $this->dispatcher->method('dispatch');

        $this->handler->handle([
            'type' => 'subscription.updated',
            'subscriptionId' => 99,
        ]);
    }

    public function testHandleSubscriptionEventWithoutSubscriptionIdIsIgnored(): void
    {
        $this->subscriptionRepo->expects($this->never())->method('findByPaymentApiId');
        $this->dispatcher->method('dispatch');

        $this->handler->handle(['type' => 'subscription.updated']);
    }

    public function testHandlePaymentUpdatedSyncsPayment(): void
    {
        $payment = new Payment();
        $payment->setPaymentApiPaymentId(77)->setTenantId(1)->setUserId(1)
            ->setProvider(PaymentProvider::MOLLIE)->setAmountCents(100)->setCurrency('EUR')
            ->setStatus(PaymentStatus::PENDING);

        $this->paymentRepo->expects($this->once())
            ->method('findByPaymentApiId')->with(77)->willReturn($payment);

        $this->paymentService->expects($this->once())
            ->method('syncPayment')->with($payment, true);

        $this->dispatcher->method('dispatch');

        $this->handler->handle([
            'type' => 'payment.updated',
            'paymentId' => 77,
        ]);
    }

    public function testHandlePaymentEventWithUnknownIdIsIgnored(): void
    {
        $this->paymentRepo->expects($this->once())
            ->method('findByPaymentApiId')->with(404)->willReturn(null);

        $this->paymentService->expects($this->never())->method('syncPayment');
        $this->dispatcher->method('dispatch');

        $this->handler->handle(['type' => 'payment.updated', 'paymentId' => 404]);
    }

    public function testWebhookReceivedEventContainsCorrectData(): void
    {
        $capturedEvent = null;
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($event) use (&$capturedEvent) {
                $capturedEvent = $event;
                return $event;
            });

        $this->handler->handle([
            'type' => 'subscription.cancelled',
            'subscriptionId' => 10,
            'paymentId' => 20,
            'data' => ['reason' => 'user_request'],
        ]);

        $this->assertInstanceOf(WebhookReceivedEvent::class, $capturedEvent);
        $this->assertSame('subscription.cancelled', $capturedEvent->getType());
        $this->assertSame(10, $capturedEvent->getPaymentApiSubscriptionId());
        $this->assertSame(20, $capturedEvent->getPaymentApiPaymentId());
    }
}
