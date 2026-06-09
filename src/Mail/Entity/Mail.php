<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Entity;

use VanDerSangen\ProjectTemplateBundle\Mail\Enum\MailStatus;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\MailRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailRepository::class)]
#[ORM\Table(name: 'mails')]
class Mail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 255)]
    private ?string $sender = null;
    #[ORM\Column(type: 'json')]
    private array $receiver = [];
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $cc = null;
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $bcc = null;
    #[ORM\Column(length: 255)]
    private ?string $title = null;
    #[ORM\Column(type: 'text')]
    private ?string $body = null;
    #[ORM\Column(length: 20)]
    private string $status = 'pending';
    /**
     * Each entry: ['path' => string, 'filename' => string, 'mime' => string].
     *
     * @var array<int, array{path: string, filename: string, mime?: string}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attachments = null;
    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(string $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getReceiver(): array
    {
        return $this->receiver;
    }

    public function setReceiver(array $receiver): static
    {
        $this->receiver = $receiver;
        return $this;
    }

    public function getCc(): ?array
    {
        return $this->cc;
    }

    public function setCc(?array $cc): static
    {
        $this->cc = $cc;
        return $this;
    }

    public function getBcc(): ?array
    {
        return $this->bcc;
    }

    public function setBcc(?array $bcc): static
    {
        $this->bcc = $bcc;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getStatus(): MailStatus
    {
        return MailStatus::from($this->status);
    }

    public function setStatus(MailStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }

    /**
     * @return array<int, array{path?: string, content?: string, filename: string, mime?: string}>|null
     */
    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    /**
     * @param array<int, array{path?: string, content?: string, filename: string, mime?: string}>|null $attachments
     */
    public function setAttachments(?array $attachments): static
    {
        $this->attachments = $attachments;
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

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'attachments' => $this->attachments,
            'createdAt' => $this->createdAt?->format('c'),
            'sentAt' => $this->sentAt?->format('c'),
        ];
    }
}
