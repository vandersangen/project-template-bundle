<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Entity;

use VanDerSangen\ProjectTemplateBundle\Queue\Enum\QueueJobLogStatus;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QueueJobLogRepository::class)]
#[ORM\Table(name: 'queue_job_logs')]
class QueueJobLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $messageClass = null;

    #[ORM\Column(type: 'json')]
    private array $messageData = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $stdout = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $stderr = null;

    #[ORM\Column(length: 20)]
    private string $status = 'started';

    #[ORM\Column]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->startedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessageClass(): ?string
    {
        return $this->messageClass;
    }

    public function setMessageClass(string $messageClass): static
    {
        $this->messageClass = $messageClass;
        return $this;
    }

    public function getMessageData(): array
    {
        return $this->messageData;
    }

    public function setMessageData(array $messageData): static
    {
        $this->messageData = $messageData;
        return $this;
    }

    public function getStdout(): ?string
    {
        return $this->stdout;
    }

    public function setStdout(?string $stdout): static
    {
        $this->stdout = $stdout;
        return $this;
    }

    public function getStderr(): ?string
    {
        return $this->stderr;
    }

    public function setStderr(?string $stderr): static
    {
        $this->stderr = $stderr;
        return $this;
    }

    public function getStatus(): QueueJobLogStatus
    {
        return QueueJobLogStatus::from($this->status);
    }

    public function setStatus(QueueJobLogStatus $status): static
    {
        $this->status = $status->value;
        return $this;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'messageClass' => $this->messageClass,
            'messageData' => $this->messageData,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'status' => $this->status,
            'startedAt' => $this->startedAt?->format('c'),
            'completedAt' => $this->completedAt?->format('c'),
        ];
    }
}
