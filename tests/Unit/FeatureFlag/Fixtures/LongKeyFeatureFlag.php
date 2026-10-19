<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;

/**
 * A flag whose key (51 characters) does not fit feature_flags.feature_flag.
 */
enum LongKeyFeatureFlag: string implements FeatureFlagInterface
{
    case TOO_LONG = 'a_flag_key_of_fifty_one_characters_which_is_too_lng';

    public function key(): string
    {
        return $this->value;
    }

    public function describe(): string
    {
        return 'Te lang';
    }

    public function backgroundAudience(): FeatureFlagAudienceInterface
    {
        return TestFeatureFlagAudience::CUSTOMERS;
    }
}
