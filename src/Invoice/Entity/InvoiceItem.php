<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Entity;

use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\Invoice\Repository\InvoiceItemRepository;

#[ORM\Entity(repositoryClass: InvoiceItemRepository::class)]
#[ORM\Table(name: 'invoice_items')]
class InvoiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\Column(length: 512)]
    private ?string $description = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column]
    private int $unitPriceCents = 0;

    #[ORM\Column]
    private int $netCents = 0;

    #[ORM\Column]
    private int $vatCents = 0;

    #[ORM\Column]
    private int $grossCents = 0;

    /** VAT rate in basis points (10000 = 100%). */
    #[ORM\Column]
    private int $vatRate = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPriceCents(): int
    {
        return $this->unitPriceCents;
    }

    public function setUnitPriceCents(int $unitPriceCents): static
    {
        $this->unitPriceCents = $unitPriceCents;
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPriceCents' => $this->unitPriceCents,
            'netCents' => $this->netCents,
            'vatCents' => $this->vatCents,
            'grossCents' => $this->grossCents,
            'vatRate' => $this->vatRate,
        ];
    }
}
