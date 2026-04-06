<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Payment\Controller\SubscriptionController;
use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Tenant\Entity\Tenant;
use VanDerSangen\ProjectTemplateBundle\Tenant\Repository\TenantRepository;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionControllerTest extends TestCase
{
    private PaymentService&MockObject $paymentService;
    private SubscriptionRepository&MockObject $subscriptionRepository;
    private PaymentRepository&MockObject $paymentRepository;
    private TenantRepository&MockObject $tenantRepository;
    private SubscriptionController $controller;
    private User $user;

    protected function setUp(): void
    {
        $this->paymentService = $this->createMock(PaymentService::class);
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->tenantRepository = $this->createMock(TenantRepository::class);

        $this->controller = new class (
            $this->paymentService,
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->tenantRepository
        ) extends SubscriptionController {
            private ?User $mockUser = null;
            
            public function setMockUser(?User $user): void
            {
                $this->mockUser = $user;
            }
            
            protected function getUser(): ?User
            {
                return $this->mockUser;
            }
        };

        $this->user = $this->createUser(1, 'test@example.com');
        $this->controller->setMockUser($this->user);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    private function createUser(int $id, string $email): User
    {
        $user = new User();
        $user->setEmail($email)->setPassword('hashed')->setFirstName('Test')->setLastName('User');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
        return $user;
    }

    private function createTenant(int $id, int $ownerUserId): Tenant
    {
        $tenant = new Tenant();
        $tenant->setName('Test Tenant')->setOwnerUserId($ownerUserId);
        $ref = new \ReflectionProperty(Tenant::class, 'id');
        $ref->setValue($tenant, $id);
        return $tenant;
    }

    private function createSubscription(
        int $id,
        int $userId,
        SubscriptionStatus $status = SubscriptionStatus::ACTIVE
    ): Subscription {
        $subscription = new Subscription();
        $subscription->setTenantId(1)
            ->setUserId($userId)
            ->setToolUserReference('tenant-1')
            ->setStatus($status)
            ->setProvider(PaymentProvider::MOLLIE)
            ->setAmountCents(999)
            ->setCurrency('EUR')
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setCheckoutUrl('https://checkout.example.com');
        $ref = new \ReflectionProperty(Subscription::class, 'id');
        $ref->setValue($subscription, $id);
        return $subscription;
    }

    // ==================== create ====================

    public function testCreateSubscriptionSuccess(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')
            ->with(['ownerUserId' => 1])
            ->willReturn($tenant);
        
        $this->subscriptionRepository->method('findActiveByTenantId')->willReturn(null);

        $subscription = $this->createSubscription(1, 1, SubscriptionStatus::PENDING);
        $this->paymentService->expects($this->once())
            ->method('createSubscription')
            ->willReturn($subscription);

        $request = new Request([], [], [], [], [], [], json_encode([
            'amountCents' => 999,
            'returnUrl' => 'https://example.com/success',
            'interval' => 'monthly',
        ]));

        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('pending', $data['status']);
        $this->assertArrayHasKey('checkoutUrl', $data);
    }

    public function testCreateSubscriptionWithoutTenant(): void
    {
        $this->tenantRepository->method('findOneBy')->willReturn(null);

        $request = new Request([], [], [], [], [], [], json_encode([
            'amountCents' => 999,
            'returnUrl' => 'https://example.com/success',
        ]));

        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('tenant', strtolower((string) $data['error']));
    }

    public function testCreateSubscriptionWhenAlreadyActive(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')->willReturn($tenant);
        
        $existingSubscription = $this->createSubscription(1, 1);
        $this->subscriptionRepository->method('findActiveByTenantId')
            ->with(1)
            ->willReturn($existingSubscription);

        $request = new Request([], [], [], [], [], [], json_encode([
            'amountCents' => 999,
            'returnUrl' => 'https://example.com/success',
        ]));

        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testCreateSubscriptionMissingRequiredFields(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')->willReturn($tenant);
        $this->subscriptionRepository->method('findActiveByTenantId')->willReturn(null);

        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('required', strtolower((string) $data['error']));
    }

    public function testCreateSubscriptionInvalidProvider(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')->willReturn($tenant);
        $this->subscriptionRepository->method('findActiveByTenantId')->willReturn(null);

        $request = new Request([], [], [], [], [], [], json_encode([
            'amountCents' => 999,
            'returnUrl' => 'https://example.com/success',
            'provider' => 'invalid_provider',
        ]));

        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    // ==================== current ====================

    public function testCurrentSubscriptionSuccess(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')->willReturn($tenant);
        
        $subscription = $this->createSubscription(1, 1);
        $this->subscriptionRepository->method('findActiveByTenantId')->willReturn($subscription);

        $response = $this->controller->current();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('active', $data['status']);
        $this->assertEquals(999, $data['amountCents']);
    }

    public function testCurrentSubscriptionNotFound(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')->willReturn($tenant);
        $this->subscriptionRepository->method('findActiveByTenantId')->willReturn(null);
        $this->subscriptionRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->current();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    // ==================== get ====================

    public function testGetSubscriptionSuccess(): void
    {
        $subscription = $this->createSubscription(1, 1);
        $this->subscriptionRepository->method('find')->with(1)->willReturn($subscription);

        $response = $this->controller->get(1);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGetSubscriptionNotFound(): void
    {
        $this->subscriptionRepository->method('find')->willReturn(null);

        $response = $this->controller->get(999);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetSubscriptionForbiddenForOtherUser(): void
    {
        $subscription = $this->createSubscription(1, 999);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $response = $this->controller->get(1);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    // ==================== cancel ====================

    public function testCancelSubscriptionSuccess(): void
    {
        $subscription = $this->createSubscription(1, 1, SubscriptionStatus::ACTIVE);
        $this->subscriptionRepository->method('find')->with(1)->willReturn($subscription);

        $cancelledSubscription = $this->createSubscription(1, 1, SubscriptionStatus::PENDING_CANCELLATION);
        $this->paymentService->method('cancelSubscription')->willReturn($cancelledSubscription);

        $request = new Request([], [], [], [], [], [], json_encode([
            'immediate' => false,
            'reason' => 'user_request',
        ]));

        $response = $this->controller->cancel(1, $request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('pending_cancellation', $data['status']);
    }

    public function testCancelSubscriptionNotFound(): void
    {
        $this->subscriptionRepository->method('find')->willReturn(null);

        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $this->controller->cancel(999, $request);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCancelSubscriptionForbidden(): void
    {
        $subscription = $this->createSubscription(1, 999);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $this->controller->cancel(1, $request);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testCancelSubscriptionNotCancellable(): void
    {
        $subscription = $this->createSubscription(1, 1, SubscriptionStatus::CANCELLED);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $this->controller->cancel(1, $request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    // ==================== sync ====================

    public function testSyncSubscriptionSuccess(): void
    {
        $subscription = $this->createSubscription(1, 1);
        $this->subscriptionRepository->method('find')->with(1)->willReturn($subscription);
        $this->paymentService->method('syncSubscription')->willReturn($subscription);

        $response = $this->controller->sync(1);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('amountCents', $data);
        $this->assertArrayHasKey('currency', $data);
        $this->assertArrayHasKey('interval', $data);
        $this->assertArrayHasKey('chargeCount', $data);
    }

    public function testSyncSubscriptionNotFound(): void
    {
        $this->subscriptionRepository->method('find')->willReturn(null);

        $response = $this->controller->sync(999);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSyncSubscriptionForbidden(): void
    {
        $subscription = $this->createSubscription(1, 999);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $response = $this->controller->sync(1);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    // ==================== payments ====================

    public function testPaymentsReturnsListForOwnSubscription(): void
    {
        $subscription = $this->createSubscription(1, 1);
        $this->subscriptionRepository->method('find')->with(1)->willReturn($subscription);

        $payment = new \VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment();
        $payment->setPaymentApiPaymentId(99)->setTenantId(1)->setUserId(1)
            ->setProvider(\VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider::MOLLIE)
            ->setAmountCents(999)->setCurrency('EUR')
            ->setStatus(\VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus::PAID);

        $this->paymentRepository->method('findBySubscriptionId')->with(1)->willReturn([$payment]);

        $response = $this->controller->payments(1);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('paid', $data[0]['status']);
    }

    public function testPaymentsNotFoundReturns404(): void
    {
        $this->subscriptionRepository->method('find')->willReturn(null);

        $response = $this->controller->payments(999);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testPaymentsForbiddenForOtherUser(): void
    {
        $subscription = $this->createSubscription(1, 999);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $response = $this->controller->payments(1);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    // ==================== settings ====================

    public function testSettingsReturnsToolSettings(): void
    {
        $this->paymentService->expects($this->once())
            ->method('getToolSettings')
            ->willReturn(['billingDay' => 1, 'currency' => 'EUR']);

        $response = $this->controller->settings();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['billingDay']);
    }

    public function testSettingsReturns500OnException(): void
    {
        $this->paymentService->method('getToolSettings')
            ->willThrowException(new \RuntimeException('API unreachable'));

        $response = $this->controller->settings();

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    // ==================== retryPayment ====================

    public function testRetryPaymentSuccessForPastDueSubscription(): void
    {
        $subscription = $this->createSubscription(1, 1, SubscriptionStatus::PAST_DUE);

        $ref = new \ReflectionProperty(Subscription::class, 'paymentApiSubscriptionId');
        $ref->setValue($subscription, 42);

        $this->subscriptionRepository->method('find')->with(1)->willReturn($subscription);

        $payment = new \VanDerSangen\ProjectTemplateBundle\Payment\Entity\Payment();
        $payment->setPaymentApiPaymentId(101)->setTenantId(1)->setUserId(1)
            ->setProvider(\VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider::MOLLIE)
            ->setAmountCents(999)->setCurrency('EUR')
            ->setStatus(\VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus::PENDING)
            ->setCheckoutUrl('https://checkout.example.com/retry');

        $this->paymentService->expects($this->once())
            ->method('retrySubscriptionPayment')
            ->willReturn($payment);

        $request = new Request([], [], [], [], [], [], json_encode(['returnUrl' => 'https://example.com/return']));

        $response = $this->controller->retryPayment(1, $request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('checkoutUrl', $data);
    }

    public function testRetryPaymentFailsWhenSubscriptionIsActive(): void
    {
        $subscription = $this->createSubscription(1, 1, SubscriptionStatus::ACTIVE);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $request = new Request([], [], [], [], [], [], json_encode(['returnUrl' => 'https://example.com/return']));

        $response = $this->controller->retryPayment(1, $request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('retry', strtolower((string) $data['error']));
    }

    public function testRetryPaymentFailsWhenReturnUrlMissing(): void
    {
        $subscription = $this->createSubscription(1, 1, SubscriptionStatus::PAST_DUE);

        $ref = new \ReflectionProperty(Subscription::class, 'paymentApiSubscriptionId');
        $ref->setValue($subscription, 42);

        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $this->controller->retryPayment(1, $request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('returnUrl', $data['error']);
    }

    public function testRetryPaymentFailsWithoutPaymentApiSubscriptionId(): void
    {
        $subscription = $this->createSubscription(1, 1, SubscriptionStatus::PAST_DUE);
        // paymentApiSubscriptionId remains null (default)

        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $request = new Request([], [], [], [], [], [], json_encode(['returnUrl' => 'https://example.com/return']));

        $response = $this->controller->retryPayment(1, $request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('payment provider', strtolower((string) $data['error']));
    }

    public function testRetryPaymentNotFoundReturns404(): void
    {
        $this->subscriptionRepository->method('find')->willReturn(null);

        $request = new Request([], [], [], [], [], [], json_encode(['returnUrl' => 'https://example.com']));
        $response = $this->controller->retryPayment(999, $request);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRetryPaymentForbiddenForOtherUser(): void
    {
        $subscription = $this->createSubscription(1, 999, SubscriptionStatus::PAST_DUE);
        $this->subscriptionRepository->method('find')->willReturn($subscription);

        $request = new Request([], [], [], [], [], [], json_encode(['returnUrl' => 'https://example.com']));
        $response = $this->controller->retryPayment(1, $request);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    // ==================== auth ====================

    public function testUnauthorizedWithoutUser(): void
    {
        $this->controller->setMockUser(null);

        $response = $this->controller->current();

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
