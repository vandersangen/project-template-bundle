<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Controller;

use VanDerSangen\ProjectTemplateBundle\Payment\Entity\Subscription;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Tenant\Repository\TenantRepository;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    #[Route('/api/subscriptions/settings', name: 'subscription_settings', methods: ['GET'])]
    public function settings(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $settings = $this->paymentService->getToolSettings();
            return $this->json($settings);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to load settings: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/subscriptions', name: 'subscription_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $this->tenantRepository->findOneBy(['ownerUserId' => $user->getId()]);
        if ($tenant === null) {
            return $this->json(['error' => 'No tenant found. Create a tenant first.'], Response::HTTP_BAD_REQUEST);
        }

        $existingSubscription = $this->subscriptionRepository->findActiveByTenantId($tenant->getId());
        if ($existingSubscription !== null) {
            return $this->json(['error' => 'Tenant already has an active subscription'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        $provider = $data['provider'] ?? 'mollie';
        $interval = $data['interval'] ?? 'monthly';
        $amountCents = $data['amountCents'] ?? null;
        $returnUrl = $data['returnUrl'] ?? null;
        $cancelUrl = $data['cancelUrl'] ?? null;

        if ($amountCents === null || $returnUrl === null) {
            return $this->json(['error' => 'amountCents and returnUrl are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $providerEnum = PaymentProvider::from($provider);
            $intervalEnum = SubscriptionInterval::from($interval);
        } catch (\ValueError $e) {
            return $this->json(['error' => 'Invalid provider or interval'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $toolSettings = $this->paymentService->getToolSettings();
            $billingDay = $toolSettings['billingDay'] ?? 1;

            $subscription = $this->paymentService->createSubscription(
                tenantId: $tenant->getId(),
                userId: $user->getId(),
                provider: $providerEnum,
                amountCents: (int) $amountCents,
                interval: $intervalEnum,
                returnUrl: $returnUrl,
                currency: $data['currency'] ?? 'EUR',
                description: $data['description'] ?? null,
                cancelUrl: $cancelUrl,
                billingDay: $billingDay,
            );

            return $this->json([
                'id' => $subscription->getId(),
                'status' => $subscription->getStatus()->value,
                'checkoutUrl' => $subscription->getCheckoutUrl(),
                'amountCents' => $subscription->getAmountCents(),
                'currency' => $subscription->getCurrency(),
                'interval' => $subscription->getInterval()->value,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to create subscription: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/subscriptions/current', name: 'subscription_current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $this->tenantRepository->findOneBy(['ownerUserId' => $user->getId()]);
        if ($tenant === null) {
            return $this->json(['error' => 'No tenant found'], Response::HTTP_NOT_FOUND);
        }

        $subscription = $this->subscriptionRepository->findActiveByTenantId($tenant->getId());
        if ($subscription === null) {
            $subscription = $this->subscriptionRepository->findOneBy(
                ['tenantId' => $tenant->getId()],
                ['createdAt' => 'DESC']
            );
        }

        if ($subscription === null) {
            return $this->json(['error' => 'No subscription found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeSubscription($subscription));
    }

    #[Route('/api/subscriptions/{id}', name: 'subscription_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if ($subscription === null) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        if ($subscription->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeSubscription($subscription));
    }

    #[Route('/api/subscriptions/{id}/cancel', name: 'subscription_cancel', methods: ['PATCH'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if ($subscription === null) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        if ($subscription->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (!$subscription->getStatus()->isCancellable()) {
            return $this->json(['error' => 'Subscription cannot be cancelled'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $immediate = $data['immediate'] ?? false;
        $reason = $data['reason'] ?? 'user_request';

        // Clear pending plan change when user explicitly cancels
        $subscription->setPendingPlanChangeData(null);

        try {
            $subscription = $this->paymentService->cancelSubscription(
                subscription: $subscription,
                immediate: (bool) $immediate,
                reason: $reason,
            );

            return $this->json([
                'id' => $subscription->getId(),
                'status' => $subscription->getStatus()->value,
                'endsAt' => $subscription->getEndsAt()?->format('c'),
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to cancel subscription: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/subscriptions/{id}/change-plan', name: 'subscription_change_plan', methods: ['PATCH'])]
    public function changePlan(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if ($subscription === null) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        if ($subscription->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (!$subscription->getStatus()->isActive()) {
            return $this->json(
                ['error' => 'Plan can only be changed on an active subscription'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $amountCents = $data['amountCents'] ?? null;
        $interval = $data['interval'] ?? null;
        $returnUrl = $data['returnUrl'] ?? null;

        if ($amountCents === null || $interval === null || $returnUrl === null) {
            return $this->json(
                ['error' => 'amountCents, interval and returnUrl are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $intervalEnum = SubscriptionInterval::from($interval);
        } catch (\ValueError) {
            return $this->json(['error' => 'Invalid interval'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $subscription = $this->paymentService->changePlan(
                subscription: $subscription,
                newAmountCents: (int) $amountCents,
                newInterval: $intervalEnum,
                returnUrl: $returnUrl,
            );

            return $this->json($this->serializeSubscription($subscription));
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to change plan: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/subscriptions/{id}/payments', name: 'subscription_payments', methods: ['GET'])]
    public function payments(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if ($subscription === null) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        if ($subscription->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $payments = $this->paymentRepository->findBySubscriptionId($id);

        return $this->json(array_map(fn ($p) => $p->toArray(), $payments));
    }

    #[Route('/api/subscriptions/{id}/retry-payment', name: 'subscription_retry_payment', methods: ['POST'])]
    public function retryPayment(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if ($subscription === null) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        if ($subscription->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $retryableStatuses = [
            SubscriptionStatus::PENDING,
            SubscriptionStatus::PAST_DUE,
            SubscriptionStatus::VERIFICATION_FAILED,
        ];

        if (!in_array($subscription->getStatus(), $retryableStatuses, true)) {
            return $this->json(
                ['error' => 'Subscription does not require a retry payment'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $returnUrl = $data['returnUrl'] ?? null;

        if ($returnUrl === null) {
            return $this->json(['error' => 'returnUrl is required'], Response::HTTP_BAD_REQUEST);
        }

        $paymentApiSubscriptionId = $subscription->getPaymentApiSubscriptionId();
        if ($paymentApiSubscriptionId === null) {
            return $this->json(
                ['error' => 'Subscription has no payment provider reference'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $payment = $this->paymentService->retrySubscriptionPayment(
                subscription: $subscription,
                returnUrl: $returnUrl,
                cancelUrl: $data['cancelUrl'] ?? null,
            );

            return $this->json(['checkoutUrl' => $payment->getCheckoutUrl()]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to create retry payment: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/api/subscriptions/{id}/sync', name: 'subscription_sync', methods: ['POST'])]
    public function sync(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if ($subscription === null) {
            return $this->json(['error' => 'Subscription not found'], Response::HTTP_NOT_FOUND);
        }

        if ($subscription->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $subscription = $this->paymentService->syncSubscription($subscription, true);

            return $this->json($this->serializeSubscription($subscription));
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to sync subscription: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function serializeSubscription(Subscription $subscription): array
    {
        $status = $subscription->getStatus();
        $needsPayment = !in_array($status, [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::CANCELLED,
            SubscriptionStatus::PENDING_CANCELLATION,
        ], true);

        $pendingData = $subscription->getPendingPlanChangeData();

        return [
            'id' => $subscription->getId(),
            'status' => $status->value,
            'amountCents' => $subscription->getAmountCents(),
            'firstPaymentAmountCents' => $subscription->getFirstPaymentAmountCents(),
            'currency' => $subscription->getCurrency(),
            'interval' => $subscription->getInterval()->value,
            'nextBillingDate' => $subscription->getNextBillingDate()?->format('c'),
            'endsAt' => $subscription->getEndsAt()?->format('c'),
            'chargeCount' => $subscription->getChargeCount(),
            'createdAt' => $subscription->getCreatedAt()?->format('c'),
            'checkoutUrl' => $needsPayment ? $subscription->getCheckoutUrl() : null,
            'pendingPlanChange' => $pendingData !== null ? [
                'amountCents' => $pendingData['amountCents'],
                'interval' => $pendingData['interval'],
            ] : null,
        ];
    }
}
