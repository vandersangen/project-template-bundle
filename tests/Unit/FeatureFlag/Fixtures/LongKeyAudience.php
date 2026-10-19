<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;

/**
 * An audience whose key (21 characters) does not fit feature_flags.audience.
 */
enum LongKeyAudience: string implements FeatureFlagAudienceInterface
{
    case TOO_LONG = 'audience_key_too_long';

    public function key(): string
    {
        return $this->value;
    }

    public function describe(): string
    {
        return 'Te lang';
    }
}
