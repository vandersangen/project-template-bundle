<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Entity;

use VanDerSangen\ProjectTemplateBundle\Cron\Repository\CronRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CronRepository::class)]
#[ORM\Table(name: 'crons')]
class Cron
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $command = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $commandArguments = null;

    #[ORM\Column(length: 100)]
    private ?string $schedule = null;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastRunAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $nextRunAt = null;

    #[ORM\Column(length: 63, nullable: true)]
    private ?string $timezone = 'UTC';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function setCommand(string $command): static
    {
        $this->command = $command;
        return $this;
    }

    public function getCommandArguments(): ?array
    {
        return $this->commandArguments;
    }

    public function setCommandArguments(?array $commandArguments): static
    {
        $this->commandArguments = $commandArguments;
        return $this;
    }

    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    public function setSchedule(string $schedule): static
    {
        $this->schedule = $schedule;
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

    public function getLastRunAt(): ?DateTimeImmutable
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?DateTimeImmutable $lastRunAt): static
    {
        $this->lastRunAt = $lastRunAt;
        return $this;
    }

    public function getNextRunAt(): ?DateTimeImmutable
    {
        return $this->nextRunAt;
    }

    public function setNextRunAt(?DateTimeImmutable $nextRunAt): static
    {
        $this->nextRunAt = $nextRunAt;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'command' => $this->command,
            'commandArguments' => $this->commandArguments,
            'schedule' => $this->schedule,
            'enabled' => $this->enabled,
            'lastRunAt' => $this->lastRunAt?->format('c'),
            'nextRunAt' => $this->nextRunAt?->format('c'),
            'timezone' => $this->timezone,
        ];
    }
}
