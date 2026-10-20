<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagAudienceRepository;

/**
 * Whether a feature flag is on for one audience, and who switched it last.
 *
 * A flag without a row for an audience is off for that audience. The audience is stored by its key, as a plain
 * string, so a row of an audience the project no longer has can still be read (and is ignored).
 */
#[ORM\Entity(repositoryClass: FeatureFlagAudienceRepository::class)]
#[ORM\Table(name: 'feature_flag_audiences')]
#[ORM\UniqueConstraint(name: 'uniq_feature_flag_audiences_flag_audience', columns: ['feature_flag_id', 'audience'])]
class FeatureFlagAudience
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FeatureFlag::class)]
    #[ORM\JoinColumn(name: 'feature_flag_id', nullable: false, onDelete: 'CASCADE')]
    private FeatureFlag $featureFlag;

    #[ORM\Column(length: 20)]
    private string $audience;

    #[ORM\Column(name: 'is_on')]
    private bool $isOn;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'updated_by', length: 180)]
    private string $updatedBy;

    /**
     * @param FeatureFlag $featureFlag The flag.
     * @param string      $audience    The key of the audience.
     * @param bool        $on          Whether the flag is on for the audience.
     * @param string      $updatedBy   Who switched it.
     */
    public function __construct(FeatureFlag $featureFlag, string $audience, bool $on, string $updatedBy)
    {
        $this->featureFlag = $featureFlag;
        $this->audience = $audience;
        $this->isOn = $on;
        $this->updatedAt = new DateTimeImmutable();
        $this->updatedBy = $updatedBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeatureFlag(): FeatureFlag
    {
        return $this->featureFlag;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function isOn(): bool
    {
        return $this->isOn;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUpdatedBy(): string
    {
        return $this->updatedBy;
    }

    /**
     * @param bool   $on        Whether the flag is on for the audience from now on.
     * @param string $updatedBy Who switched it.
     */
    public function switchTo(bool $on, string $updatedBy): void
    {
        $this->isOn = $on;
        $this->updatedAt = new DateTimeImmutable();
        $this->updatedBy = $updatedBy;
    }
}
