<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\Invoice\Enum\VatMode;
use VanDerSangen\ProjectTemplateBundle\Invoice\Repository\InvoiceTemplateRepository;

/**
 * Per-owner structured invoice branding/settings. The owning application keys
 * a template via $ownerKey (e.g. "tool:42"); fields left null fall back to the
 * platform defaults at render time.
 */
#[ORM\Entity(repositoryClass: InvoiceTemplateRepository::class)]
#[ORM\Table(name: 'invoice_templates')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_template_owner', columns: ['owner_key'])]
class InvoiceTemplate
{
    public const int DEFAULT_VAT_RATE = 2100;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    private ?string $ownerKey = null;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $houseNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cocNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $footerText = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $accentColor = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $numberPrefix = null;

    /** VAT rate in basis points (10000 = 100%, so 2100 = 21%). */
    #[ORM\Column]
    private int $vatRate = self::DEFAULT_VAT_RATE;

    #[ORM\Column(length: 16)]
    private string $vatMode = VatMode::INCLUSIVE->value;

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

    public function getOwnerKey(): ?string
    {
        return $this->ownerKey;
    }

    public function setOwnerKey(string $ownerKey): static
    {
        $this->ownerKey = $ownerKey;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;
        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;
        return $this;
    }

    public function getHouseNumber(): ?string
    {
        return $this->houseNumber;
    }

    public function setHouseNumber(?string $houseNumber): static
    {
        $this->houseNumber = $houseNumber;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;
        return $this;
    }

    public function getCocNumber(): ?string
    {
        return $this->cocNumber;
    }

    public function setCocNumber(?string $cocNumber): static
    {
        $this->cocNumber = $cocNumber;
        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
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

    public function getNumberPrefix(): ?string
    {
        return $this->numberPrefix;
    }

    public function setNumberPrefix(?string $numberPrefix): static
    {
        $this->numberPrefix = $numberPrefix;
        return $this;
    }

    public function getVatRate(): int
    {
        return $this->vatRate;
    }

    public function setVatRate(int $vatRate): static
    {
        $this->vatRate = max(0, $vatRate);
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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ownerKey' => $this->ownerKey,
            'enabled' => $this->enabled,
            'logoPath' => $this->logoPath,
            'companyName' => $this->companyName,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'vatNumber' => $this->vatNumber,
            'cocNumber' => $this->cocNumber,
            'iban' => $this->iban,
            'email' => $this->email,
            'footerText' => $this->footerText,
            'accentColor' => $this->accentColor,
            'numberPrefix' => $this->numberPrefix,
            'vatRate' => $this->vatRate,
            'vatMode' => $this->vatMode,
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
