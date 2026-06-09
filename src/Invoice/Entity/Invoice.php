<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\InvoiceStatus;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\VatMode;
use VanDerSangen\ProjectTemplateBundle\Invoice\Repository\InvoiceRepository;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_source', columns: ['owner_key', 'source_type', 'source_id'])]
#[ORM\UniqueConstraint(name: 'uniq_invoice_number', columns: ['owner_key', 'number'])]
#[ORM\Index(columns: ['owner_key'], name: 'idx_invoices_owner_key')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $number = null;

    #[ORM\Column(length: 191)]
    private ?string $ownerKey = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $sourceId = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $issuer = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $customer = [];

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column]
    private int $netCents = 0;

    #[ORM\Column]
    private int $vatCents = 0;

    #[ORM\Column]
    private int $grossCents = 0;

    /** VAT rate in basis points (10000 = 100%, so 2100 = 21%). */
    #[ORM\Column]
    private int $vatRate = 0;

    #[ORM\Column(length: 16)]
    private string $vatMode = VatMode::INCLUSIVE->value;

    #[ORM\Column(length: 16)]
    private string $status = InvoiceStatus::DRAFT->value;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $footerText = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $accentColor = null;

    /**
     * The rendered PDF stored as a binary blob (LONGBLOB). After hydration
     * Doctrine exposes this as a stream resource; the accessor normalises it
     * back to a string.
     *
     * @var resource|string|null
     */
    #[ORM\Column(type: Types::BLOB, nullable: true, options: ['length' => 4294967295])]
    private mixed $pdfContent = null;

    #[ORM\Column]
    private ?DateTimeImmutable $issuedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    /** @var Collection<int, InvoiceItem> */
    #[ORM\OneToMany(targetEntity: InvoiceItem::class, mappedBy: 'invoice', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->issuedAt = new DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;
        return $this;
    }

    public function getOwnerKey(): ?string
    {
        return $this->ownerKey;
    }

    public function setOwnerKey(string $ownerKey): static
    {
        $this->ownerKey = $ownerKey;
        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): static
    {
        $this->sourceType = $sourceType;
        return $this;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(?string $sourceId): static
    {
        $this->sourceId = $sourceId;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getIssuer(): array
    {
        return $this->issuer;
    }

    /** @param array<string, mixed> $issuer */
    public function setIssuer(array $issuer): static
    {
        $this->issuer = $issuer;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getCustomer(): array
    {
        return $this->customer;
    }

    /** @param array<string, mixed> $customer */
    public function setCustomer(array $customer): static
    {
        $this->customer = $customer;
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

    public function getNetCents(): int
    {
        return $this->netCents;
    }

    public function setNetCents(int $netCents): static
    {
        $this->netCents = $netCents;
        return $this;
    }

    public function getVatCents(): int
    {
        return $this->vatCents;
    }

    public function setVatCents(int $vatCents): static
    {
        $this->vatCents = $vatCents;
        return $this;
    }

    public function getGrossCents(): int
    {
        return $this->grossCents;
    }

    public function setGrossCents(int $grossCents): static
    {
        $this->grossCents = $grossCents;
        return $this;
    }

    public function getVatRate(): int
    {
        return $this->vatRate;
    }

    public function setVatRate(int $vatRate): static
    {
        $this->vatRate = $vatRate;
        return $this;
    }

    public function getVatMode(): VatMode
    {
        return VatMode::from($this->vatMode);
    }

    public function setVatMode(VatMode $vatMode): static
    {
        $this->vatMode = $vatMode->value;
        return $this;
    }

    public function getStatus(): InvoiceStatus
    {
        return InvoiceStatus::from($this->status);
    }

    public function setStatus(InvoiceStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }

    public function getFooterText(): ?string
    {
        return $this->footerText;
    }

    public function setFooterText(?string $footerText): static
    {
        $this->footerText = $footerText;
        return $this;
    }

    public function getAccentColor(): ?string
    {
        return $this->accentColor;
    }

    public function setAccentColor(?string $accentColor): static
    {
        $this->accentColor = $accentColor;
        return $this;
    }

    public function getPdfContent(): ?string
    {
        if ($this->pdfContent === null) {
            return null;
        }
        if (is_resource($this->pdfContent)) {
            rewind($this->pdfContent);
            $contents = stream_get_contents($this->pdfContent);
            return $contents === false ? null : $contents;
        }
        return $this->pdfContent;
    }

    public function setPdfContent(?string $pdfContent): static
    {
        $this->pdfContent = $pdfContent;
        return $this;
    }

    public function hasPdf(): bool
    {
        return $this->pdfContent !== null;
    }

    public function getIssuedAt(): ?DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, InvoiceItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InvoiceItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInvoice($this);
        }
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'ownerKey' => $this->ownerKey,
            'sourceType' => $this->sourceType,
            'sourceId' => $this->sourceId,
            'issuer' => $this->issuer,
            'customer' => $this->customer,
            'currency' => $this->currency,
            'netCents' => $this->netCents,
            'vatCents' => $this->vatCents,
            'grossCents' => $this->grossCents,
            'vatRate' => $this->vatRate,
            'vatMode' => $this->vatMode,
            'status' => $this->status,
            'hasPdf' => $this->hasPdf(),
            'issuedAt' => $this->issuedAt?->format('c'),
            'createdAt' => $this->createdAt?->format('c'),
            'items' => array_map(static fn(InvoiceItem $i): array => $i->toArray(), $this->items->toArray()),
        ];
    }
}
