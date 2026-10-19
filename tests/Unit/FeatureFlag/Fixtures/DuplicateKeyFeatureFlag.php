<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;

/**
 * Two flags with the same key, which the registry refuses.
 */
enum DuplicateKeyFeatureFlag: string implements FeatureFlagInterface
{
    case FIRST = 'first';
    case SECOND = 'second';

    public function key(): string
    {
        return 'same';
    }

    public function describe(): string
    {
        return $this->value;
    }

    public function backgroundAudience(): FeatureFlagAudienceInterface
    {
        return TestFeatureFlagAudience::CUSTOMERS;
    }
}
