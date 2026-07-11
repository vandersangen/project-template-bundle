<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\EmailTemplateRepository;

/**
 * Per-owner override of a lifecycle e-mail. The owning application keys a
 * template via $ownerKey (e.g. "tool:42") + $templateKey (an
 * {@see \VanDerSangen\ProjectTemplateBundle\Mail\Enum\EmailTemplateKey} value).
 *
 * A null subject/body falls back to the enum default at render time; $enabled
 * false suppresses the mail entirely for this owner.
 */
#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\Table(name: 'email_templates')]
#[ORM\UniqueConstraint(name: 'uniq_email_template_owner_key', columns: ['owner_key', 'template_key'])]
class EmailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    private ?string $ownerKey = null;

    #[ORM\Column(length: 64)]
    private ?string $templateKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyHtml = null;

    #[ORM\Column]
    private bool $enabled = true;

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

    public function getTemplateKey(): ?string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(string $templateKey): static
    {
        $this->templateKey = $templateKey;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(?string $bodyHtml): static
    {
        $this->bodyHtml = $bodyHtml;
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
}
