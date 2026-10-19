<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;

/**
 * Implements the interface but has no backing values, which the registry refuses.
 */
enum UnitEnumFeatureFlag implements FeatureFlagInterface
{
    case SOMETHING;

    public function key(): string
    {
        return 'something';
    }

    public function describe(): string
    {
        return 'Iets';
    }

    public function backgroundAudience(): FeatureFlagAudienceInterface
    {
        return TestFeatureFlagAudience::CUSTOMERS;
    }
}
