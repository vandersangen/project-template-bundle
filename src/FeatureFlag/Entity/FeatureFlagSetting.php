<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagSettingRepository;

/**
 * Whether a feature flag is on for one audience in this environment, and who switched it last.
 *
 * A flag without a row for an audience is off for that audience. The flag and the audience are stored by their key,
 * as plain strings, so a row of a flag or audience the project no longer has can still be read (and is ignored).
 */
#[ORM\Entity(repositoryClass: FeatureFlagSettingRepository::class)]
#[ORM\Table(name: 'feature_flags')]
#[ORM\UniqueConstraint(name: 'uniq_feature_flags_flag_audience', columns: ['feature_flag', 'audience'])]
class FeatureFlagSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'feature_flag', length: 50)]
    private string $featureFlagKey;

    #[ORM\Column(name: 'audience', length: 20)]
    private string $audienceKey;

    #[ORM\Column(name: 'is_on')]
    private bool $on;

    #[ORM\Column(name: 'updated_at')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'updated_by', length: 180)]
    private string $updatedBy;

    /**
     * @param string $featureFlagKey The key of the flag.
     * @param string $audienceKey    The key of the audience.
     * @param bool   $on             Whether the flag is on for the audience.
     * @param string $changedBy      Who switched it.
     */
    public function __construct(string $featureFlagKey, string $audienceKey, bool $on, string $changedBy)
    {
        $this->featureFlagKey = $featureFlagKey;
        $this->audienceKey = $audienceKey;
        $this->on = $on;
        $this->recordChangeBy($changedBy);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeatureFlagKey(): string
    {
        return $this->featureFlagKey;
    }

    public function getAudienceKey(): string
    {
        return $this->audienceKey;
    }

    public function isOn(): bool
    {
        return $this->on;
    }

    /**
     * @param bool   $on        Whether the flag is on for the audience from now on.
     * @param string $changedBy Who switched it.
     */
    public function switchTo(bool $on, string $changedBy): void
    {
        $this->on = $on;
        $this->recordChangeBy($changedBy);
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUpdatedBy(): string
    {
        return $this->updatedBy;
    }

    private function recordChangeBy(string $changedBy): void
    {
        $this->updatedAt = new DateTimeImmutable();
        $this->updatedBy = $changedBy;
    }
}
