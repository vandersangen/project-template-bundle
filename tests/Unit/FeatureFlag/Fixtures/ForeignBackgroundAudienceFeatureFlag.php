<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;

/**
 * A flag whose background audience is of another enum than the configured audiences.
 */
enum ForeignBackgroundAudienceFeatureFlag: string implements FeatureFlagInterface
{
    case ELSEWHERE = 'elsewhere';

    public function key(): string
    {
        return $this->value;
    }

    public function describe(): string
    {
        return 'Elders';
    }

    public function backgroundAudience(): FeatureFlagAudienceInterface
    {
        return LongKeyAudience::TOO_LONG;
    }
}
