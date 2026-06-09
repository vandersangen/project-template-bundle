<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Invoice\Entity;

use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\Invoice\Repository\InvoiceNumberSequenceRepository;

/**
 * Per-owner, per-year counter used to assign gap-free, sequential invoice
 * numbers. Rows are locked pessimistically while incrementing.
 */
#[ORM\Entity(repositoryClass: InvoiceNumberSequenceRepository::class)]
#[ORM\Table(name: 'invoice_number_sequences')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_sequence_owner_year', columns: ['owner_key', 'year'])]
class InvoiceNumberSequence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191)]
    private ?string $ownerKey = null;

    #[ORM\Column]
    private int $year = 0;

    #[ORM\Column]
    private int $counter = 0;

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

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): static
    {
        $this->counter = $counter;
        return $this;
    }

    public function increment(): int
    {
        $this->counter++;
        return $this->counter;
    }
}
