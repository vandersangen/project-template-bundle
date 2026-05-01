<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Entity;

use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'payment_subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Foreign key to tenants.id */
    #[ORM\Column]
    private ?int $tenantId = null;

    /** Foreign key to users.id — the user who initiated the subscription */
    #[ORM\Column]
    private ?int $userId = null;

    /** Identifier sent to payment-api as toolUserReference (e.g. "tenant-42") */
    #[ORM\Column(length: 255)]
    private ?string $toolUserReference = null;

    /** Payment-api internal subscription ID */
    #[ORM\Column(nullable: true)]
    private ?int $paymentApiSubscriptionId = null;

    #[ORM\Column(length: 20)]
    private string $provider = PaymentProvider::MOLLIE->value;

    #[ORM\Column(length: 30)]
    private string $status = SubscriptionStatus::PENDING->value;

    #[ORM\Column]
    private int $amountCents = 0;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(name: 'subscription_interval', length: 20)]
    private string $interval = SubscriptionInterval::MONTHLY->value;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /** URL returned by the payment-api to redirect the user to the checkout flow */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $checkoutUrl = null;

    /** Pro-rata amount charged for the first (partial) billing period, in cents. */
    #[ORM\Column(nullable: true)]
    private ?int $firstPaymentAmountCents = null;

    /** Provider-side subscription ID (e.g. sub_abc123) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerSubscriptionId = null;

    /** Provider-side customer ID (e.g. cus_xyz789) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerCustomerId = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $nextBillingDate = null;

    #[ORM\Column]
    private int $failedChargeCount = 0;

    /**
     * When set, the subscription will stop after this many successful charges.
     * Null means unlimited. Set to 1 to allow exactly one more charge after cancellation request.
     */
    #[ORM\Column(nullable: true)]
    private ?int $maxCharges = null;

    #[ORM\Column]
    private int $chargeCount = 0;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $endsAt = null;

    /** Pending plan change data: {amountCents, interval, returnUrl}. Set when user requests a plan switch. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pendingPlanChangeData = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'subscription')]
    private Collection $payments;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function setTenantId(int $tenantId): static
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getToolUserReference(): ?string
    {
        return $this->toolUserReference;
    }

    public function setToolUserReference(string $toolUserReference): static
    {
        $this->toolUserReference = $toolUserReference;
        return $this;
    }

    public function getPaymentApiSubscriptionId(): ?int
    {
        return $this->paymentApiSubscriptionId;
    }

    public function setPaymentApiSubscriptionId(?int $paymentApiSubscriptionId): static
    {
        $this->paymentApiSubscriptionId = $paymentApiSubscriptionId;
        return $this;
    }

    public function getProvider(): PaymentProvider
    {
        return PaymentProvider::from($this->provider);
    }

    public function setProvider(PaymentProvider $provider): static
    {
        $this->provider = $provider->value;
        return $this;
    }

    public function getStatus(): SubscriptionStatus
    {
        return SubscriptionStatus::from($this->status);
    }

    public function setStatus(SubscriptionStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function setAmountCents(int $amountCents): static
    {
        $this->amountCents = $amountCents;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getInterval(): SubscriptionInterval
    {
        return SubscriptionInterval::from($this->interval);
    }

    public function setInterval(SubscriptionInterval $interval): static
    {
        $this->interval = $interval->value;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCheckoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    public function setCheckoutUrl(?string $checkoutUrl): static
    {
        $this->checkoutUrl = $checkoutUrl;
        return $this;
    }

    public function getFirstPaymentAmountCents(): ?int
    {
        return $this->firstPaymentAmountCents;
    }

    public function setFirstPaymentAmountCents(?int $firstPaymentAmountCents): static
    {
        $this->firstPaymentAmountCents = $firstPaymentAmountCents;
        return $this;
    }

    public function getProviderSubscriptionId(): ?string
    {
        return $this->providerSubscriptionId;
    }

    public function setProviderSubscriptionId(?string $providerSubscriptionId): static
    {
        $this->providerSubscriptionId = $providerSubscriptionId;
        return $this;
    }

    public function getProviderCustomerId(): ?string
    {
        return $this->providerCustomerId;
    }

    public function setProviderCustomerId(?string $providerCustomerId): static
    {
        $this->providerCustomerId = $providerCustomerId;
        return $this;
    }

    public function getNextBillingDate(): ?DateTimeImmutable
    {
        return $this->nextBillingDate;
    }

    public function setNextBillingDate(?DateTimeImmutable $nextBillingDate): static
    {
        $this->nextBillingDate = $nextBillingDate;
        return $this;
    }

    public function getFailedChargeCount(): int
    {
        return $this->failedChargeCount;
    }

    public function setFailedChargeCount(int $failedChargeCount): static
    {
        $this->failedChargeCount = $failedChargeCount;
        return $this;
    }

    public function getMaxCharges(): ?int
    {
        return $this->maxCharges;
    }

    public function setMaxCharges(?int $maxCharges): static
    {
        $this->maxCharges = $maxCharges;
        return $this;
    }

    public function getChargeCount(): int
    {
        return $this->chargeCount;
    }

    public function setChargeCount(int $chargeCount): static
    {
        $this->chargeCount = $chargeCount;
        return $this;
    }

    public function incrementChargeCount(): static
    {
        $this->chargeCount++;
        return $this;
    }

    public function hasReachedMaxCharges(): bool
    {
        return $this->maxCharges !== null && $this->chargeCount >= $this->maxCharges;
    }

    public function getEndsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;
        return $this;
    }

    public function getPendingPlanChangeData(): ?array
    {
        return $this->pendingPlanChangeData;
    }

    public function setPendingPlanChangeData(?array $pendingPlanChangeData): static
    {
        $this->pendingPlanChangeData = $pendingPlanChangeData;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'userId' => $this->userId,
            'toolUserReference' => $this->toolUserReference,
            'paymentApiSubscriptionId' => $this->paymentApiSubscriptionId,
            'provider' => $this->provider,
            'status' => $this->status,
            'amountCents' => $this->amountCents,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'description' => $this->description,
            'checkoutUrl' => $this->checkoutUrl,
            'firstPaymentAmountCents' => $this->firstPaymentAmountCents,
            'providerSubscriptionId' => $this->providerSubscriptionId,
            'providerCustomerId' => $this->providerCustomerId,
            'nextBillingDate' => $this->nextBillingDate?->format('c'),
            'failedChargeCount' => $this->failedChargeCount,
            'maxCharges' => $this->maxCharges,
            'chargeCount' => $this->chargeCount,
            'endsAt' => $this->endsAt?->format('c'),
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
