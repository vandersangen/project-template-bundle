<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Service;

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
use DateTimeImmutable;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentService
{
    public function __construct(
        private readonly PaymentApiClient $apiClient,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Fetch tool-level settings from the payment-api (e.g. billingDay).
     *
     * @return array{billingDay: int}
     */
    public function getToolSettings(): array
    {
        return $this->apiClient->getToolSettings();
    }

    /**
     * Create a recurring subscription via the payment-api and persist it locally.
     * Returns the Subscription with the checkout URL to redirect the user to.
     *
     * @param int                        $tenantId    Tenant the subscription belongs to.
     * @param int                        $userId      User who started the subscription.
     * @param PaymentProvider            $provider    Payment provider.
     * @param int                        $amountCents Recurring amount in cents.
     * @param SubscriptionInterval       $interval    Billing interval.
     * @param string                     $returnUrl   URL to redirect to after checkout.
     * @param string                     $currency    Three-letter ISO currency code.
     * @param string|null                $description Subscription description.
     * @param string|null                $cancelUrl   URL to redirect to on cancel.
     * @param int                        $billingDay  Day of the month to bill on.
     * @param array<string, string>|null $customer    Customer billing details used on generated
     *                                                invoices (name, companyName, email, vatNumber,
     *                                                cocNumber, street, houseNumber, postalCode,
     *                                                city, country).
     */
    public function createSubscription(
        int $tenantId,
        int $userId,
        PaymentProvider $provider,
        int $amountCents,
        SubscriptionInterval $interval,
        string $returnUrl,
        string $currency = 'EUR',
        ?string $description = null,
        ?string $cancelUrl = null,
        int $billingDay = 1,
        ?array $customer = null,
    ): Subscription {
        $toolUserReference = sprintf('tenant-%d', $tenantId);

        $apiResponse = $this->apiClient->createSubscription(
            provider: $provider->value,
            toolUserReference: $toolUserReference,
            amountCents: $amountCents,
            returnUrl: $returnUrl,
            interval: $interval->value,
            currency: $currency,
            description: $description,
            cancelUrl: $cancelUrl,
            billingDay: $billingDay,
            customer: $customer,
        );

        $subscription = new Subscription();
        $subscription->setTenantId($tenantId)
            ->setUserId($userId)
            ->setToolUserReference($toolUserReference)
            ->setPaymentApiSubscriptionId($apiResponse['id'])
            ->setProvider($provider)
            ->setStatus(SubscriptionStatus::PENDING)
            ->setAmountCents($amountCents)
            ->setCurrency($currency)
            ->setInterval($interval)
            ->setDescription($description)
            ->setCheckoutUrl($apiResponse['checkoutUrl'] ?? null)
            ->setFirstPaymentAmountCents($apiResponse['firstPaymentAmountCents'] ?? null);

        if (isset($apiResponse['nextBillingDate'])) {
            $subscription->setNextBillingDate(new DateTimeImmutable($apiResponse['nextBillingDate']));
        }

        $this->subscriptionRepository->save($subscription, true);

        $this->eventDispatcher->dispatch(new SubscriptionCreatedEvent($subscription));

        return $subscription;
    }

    /**
     * Sync a subscription's status from the payment-api.
     */
    public function syncSubscription(Subscription $subscription, bool $forceSync = false): Subscription
    {
        if ($subscription->getPaymentApiSubscriptionId() === null) {
            return $subscription;
        }

        $apiData = $this->apiClient->getSubscription($subscription->getPaymentApiSubscriptionId(), $forceSync);

        $previousStatus = $subscription->getStatus()->value;
        $newStatus = SubscriptionStatus::from($apiData['status']);

        $subscription->setStatus($newStatus)
            ->setFailedChargeCount($apiData['failedChargeCount'] ?? 0)
            ->setProviderSubscriptionId($apiData['providerSubscriptionId'] ?? null)
            ->setProviderCustomerId($apiData['providerCustomerId'] ?? null)
            ->setUpdatedAt(new DateTimeImmutable());

        if (isset($apiData['nextBillingDate'])) {
            $subscription->setNextBillingDate(new DateTimeImmutable($apiData['nextBillingDate']));
        }

        if (isset($apiData['endsAt'])) {
            $subscription->setEndsAt(new DateTimeImmutable($apiData['endsAt']));
        }

        $this->subscriptionRepository->save($subscription, true);

        if ($previousStatus !== $newStatus->value) {
            $this->eventDispatcher->dispatch(new SubscriptionStatusChangedEvent($subscription, $previousStatus));
        }

        return $subscription;
    }

    /**
     * Cancel a subscription via the payment-api.
     *
     * Pass $allowOneMoreCharge = true to let the last scheduled charge still run before
     * the subscription is fully stopped (i.e. "nog één keer meenemen").
     *
     * @throws \LogicException
     */
    public function cancelSubscription(
        Subscription $subscription,
        bool $immediate = false,
        ?string $reason = null,
        bool $allowOneMoreCharge = false,
    ): Subscription {
        if (!$subscription->getStatus()->isCancellable()) {
            throw new \LogicException(sprintf(
                'Subscription %d cannot be cancelled from status "%s".',
                $subscription->getId(),
                $subscription->getStatus()->value,
            ));
        }

        if ($allowOneMoreCharge && !$immediate) {
            $subscription->setMaxCharges($subscription->getChargeCount() + 1);
        }

        $apiResponse = $this->apiClient->cancelSubscription(
            id: $subscription->getPaymentApiSubscriptionId(),
            immediate: $immediate,
            reason: $reason,
        );

        $previousStatus = $subscription->getStatus()->value;
        $subscription->setStatus(SubscriptionStatus::from($apiResponse['status']))
            ->setUpdatedAt(new DateTimeImmutable());

        if (isset($apiResponse['endsAt'])) {
            $subscription->setEndsAt(new DateTimeImmutable($apiResponse['endsAt']));
        }

        $this->subscriptionRepository->save($subscription, true);

        $this->eventDispatcher->dispatch(new SubscriptionCancelledEvent($subscription, $immediate, $reason));

        if ($previousStatus !== $subscription->getStatus()->value) {
            $this->eventDispatcher->dispatch(new SubscriptionStatusChangedEvent($subscription, $previousStatus));
        }

        return $subscription;
    }

    /**
     * Schedule a plan change effective after the next billing cycle.
     *
     * Marks the current subscription for cancellation after one more charge, and stores
     * the new plan details so a new subscription is automatically created afterwards.
     *
     * @throws \LogicException When subscription is not active.
     */
    public function changePlan(
        Subscription $subscription,
        int $newAmountCents,
        SubscriptionInterval $newInterval,
        string $returnUrl,
    ): Subscription {
        if (!$subscription->getStatus()->isActive()) {
            throw new \LogicException(sprintf(
                'Subscription %d cannot have its plan changed from status "%s".',
                $subscription->getId(),
                $subscription->getStatus()->value,
            ));
        }

        $subscription->setPendingPlanChangeData([
            'amountCents' => $newAmountCents,
            'interval' => $newInterval->value,
            'returnUrl' => $returnUrl,
        ]);
        $this->subscriptionRepository->save($subscription, true);

        // Cancel after one more charge so current period is honoured
        $this->cancelSubscription(
            subscription: $subscription,
            immediate: false,
            reason: 'plan_change',
            allowOneMoreCharge: true,
        );

        return $subscription;
    }

    /**
     * Create a retry payment for an existing subscription via the payment-api.
     * Uses the subscription-specific retry endpoint which creates a new mandate payment
     * linked to the subscription, with a publicly accessible webhook URL via ngrok.
     */
    public function retrySubscriptionPayment(
        Subscription $subscription,
        string $returnUrl,
        ?string $cancelUrl = null,
    ): Payment {
        $apiResponse = $this->apiClient->retrySubscriptionPayment(
            subscriptionId: $subscription->getPaymentApiSubscriptionId(),
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
        );

        $payment = new Payment();
        $payment->setTenantId($subscription->getTenantId())
            ->setUserId($subscription->getUserId())
            ->setPaymentApiPaymentId($apiResponse['id'])
            ->setProvider($subscription->getProvider())
            ->setStatus(PaymentStatus::PENDING)
            ->setAmountCents($subscription->getAmountCents())
            ->setCurrency($subscription->getCurrency())
            ->setDescription($subscription->getDescription())
            ->setCheckoutUrl($apiResponse['checkoutUrl'] ?? null)
            ->setSubscription($subscription);

        $this->paymentRepository->save($payment, true);

        $this->eventDispatcher->dispatch(new PaymentCreatedEvent($payment));

        return $payment;
    }

    /**
     * Create a one-time payment via the payment-api.
     */
    public function createPayment(
        int $tenantId,
        int $userId,
        PaymentProvider $provider,
        int $amountCents,
        string $returnUrl,
        string $currency = 'EUR',
        ?string $description = null,
        ?string $cancelUrl = null,
        ?Subscription $subscription = null,
    ): Payment {
        $apiResponse = $this->apiClient->createPayment(
            provider: $provider->value,
            amountCents: $amountCents,
            returnUrl: $returnUrl,
            currency: $currency,
            description: $description,
            cancelUrl: $cancelUrl,
        );

        $payment = new Payment();
        $payment->setTenantId($tenantId)
            ->setUserId($userId)
            ->setPaymentApiPaymentId($apiResponse['id'])
            ->setProvider($provider)
            ->setStatus(PaymentStatus::PENDING)
            ->setAmountCents($amountCents)
            ->setCurrency($currency)
            ->setDescription($description)
            ->setCheckoutUrl($apiResponse['checkoutUrl'] ?? null)
            ->setSubscription($subscription);

        $this->paymentRepository->save($payment, true);

        $this->eventDispatcher->dispatch(new PaymentCreatedEvent($payment));

        return $payment;
    }

    /**
     * Sync a single payment's status from the payment-api.
     */
    public function syncPayment(Payment $payment, bool $forceSync = false): Payment
    {
        if ($payment->getPaymentApiPaymentId() === null) {
            return $payment;
        }

        $apiData = $this->apiClient->getPayment($payment->getPaymentApiPaymentId(), $forceSync);

        $previousStatus = $payment->getStatus()->value;
        $newStatus = PaymentStatus::from($apiData['status']);

        $payment->setStatus($newStatus)
            ->setProviderPaymentId($apiData['providerPaymentId'] ?? null)
            ->setFailureReason($apiData['failureReason'] ?? null)
            ->setUpdatedAt(new DateTimeImmutable());

        if (isset($apiData['amountCents'])) {
            $payment->setAmountCents((int) $apiData['amountCents']);
        }
        if (isset($apiData['currency'])) {
            $payment->setCurrency((string) $apiData['currency']);
        }
        if (isset($apiData['description'])) {
            $payment->setDescription((string) $apiData['description']);
        }

        $this->paymentRepository->save($payment, true);

        if ($previousStatus !== $newStatus->value) {
            $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent($payment, $previousStatus));

            if ($payment->getSubscription() !== null) {
                $failedStatuses = [PaymentStatus::FAILED, PaymentStatus::EXPIRED, PaymentStatus::CANCELLED];
                if ($newStatus === PaymentStatus::PAID) {
                    // Successful charge: increment counter and sync subscription
                    $this->handleSuccessfulSubscriptionCharge($payment->getSubscription());
                } elseif (in_array($newStatus, $failedStatuses)) {
                    // Failed charge: sync subscription so its status reflects the failure
                    // (e.g. pending → payment_method_required)
                    try {
                        $this->syncSubscription($payment->getSubscription(), true);
                    } catch (\Throwable) {
                        // Non-fatal: payment record is already saved
                    }
                }
            }
        }

        return $payment;
    }

    /**
     * Check subscription access level:
     * - true  = active, may be charged
     * - false = no more charges allowed or subscription ended
     */
    public function isSubscriptionActive(Subscription $subscription): bool
    {
        if (!$subscription->getStatus()->isActive()) {
            return false;
        }

        if ($subscription->hasReachedMaxCharges()) {
            return false;
        }

        return true;
    }

    private function handleSuccessfulSubscriptionCharge(Subscription $subscription): void
    {
        $subscription->incrementChargeCount();

        if ($subscription->hasReachedMaxCharges()) {
            $this->cancelSubscription(
                subscription: $subscription,
                immediate: true,
                reason: 'max_charges_reached',
            );
            return;
        }

        // Sync subscription status from the provider (e.g. pending → active after first payment).
        // forceSync=true ensures we get the latest status rather than the payment-api's cached value.
        $this->syncSubscription($subscription, true);
    }
}
