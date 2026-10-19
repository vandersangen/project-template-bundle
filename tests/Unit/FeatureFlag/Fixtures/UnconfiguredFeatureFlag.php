<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;

/**
 * A valid flag that the test app does not configure, so the registry does not know it.
 */
enum UnconfiguredFeatureFlag: string implements FeatureFlagInterface
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
        return TestFeatureFlagAudience::CUSTOMERS;
    }
}
