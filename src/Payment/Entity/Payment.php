<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Entity;

use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentStatus;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Foreign key to tenants.id */
    #[ORM\Column]
    private ?int $tenantId = null;

    /** Foreign key to users.id */
    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\ManyToOne(targetEntity: Subscription::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Subscription $subscription = null;

    /** Payment-api internal payment ID */
    #[ORM\Column(nullable: true)]
    private ?int $paymentApiPaymentId = null;

    /** Provider-side payment ID (e.g. pi_abc123 or tr_abc123) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerPaymentId = null;

    #[ORM\Column(length: 20)]
    private string $provider = PaymentProvider::MOLLIE->value;

    #[ORM\Column(length: 20)]
    private string $status = PaymentStatus::PENDING->value;

    #[ORM\Column]
    private int $amountCents = 0;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $checkoutUrl = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
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

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;
        return $this;
    }

    public function getPaymentApiPaymentId(): ?int
    {
        return $this->paymentApiPaymentId;
    }

    public function setPaymentApiPaymentId(?int $paymentApiPaymentId): static
    {
        $this->paymentApiPaymentId = $paymentApiPaymentId;
        return $this;
    }

    public function getProviderPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function setProviderPaymentId(?string $providerPaymentId): static
    {
        $this->providerPaymentId = $providerPaymentId;
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

    public function getStatus(): PaymentStatus
    {
        return PaymentStatus::from($this->status);
    }

    public function setStatus(PaymentStatus $status): static
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'userId' => $this->userId,
            'subscriptionId' => $this->subscription?->getId(),
            'paymentApiPaymentId' => $this->paymentApiPaymentId,
            'providerPaymentId' => $this->providerPaymentId,
            'provider' => $this->provider,
            'status' => $this->status,
            'amountCents' => $this->amountCents,
            'currency' => $this->currency,
            'description' => $this->description,
            'failureReason' => $this->failureReason,
            'checkoutUrl' => $this->checkoutUrl,
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
