<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagRepository;

/**
 * A feature flag the project knows, loaded from master data (bundle:master-data:load).
 *
 * The cid is the key the project code asks for. Whether the flag is on, per audience, lives in
 * FeatureFlagAudience; this entity only describes the flag.
 */
#[ORM\Entity(repositoryClass: FeatureFlagRepository::class)]
#[ORM\Table(name: 'feature_flags')]
#[ORM\UniqueConstraint(name: 'uniq_feature_flags_cid', columns: ['cid'])]
#[ORM\HasLifecycleCallbacks]
class FeatureFlag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $cid = '';

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, length: 1000, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    /**
     * @param string $cid The key of the flag.
     */
    public function setCid(string $cid): void
    {
        $this->cid = $cid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name The name of the flag.
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description What the flag switches on, if known.
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function recordUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
