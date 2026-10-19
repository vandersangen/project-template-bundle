<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;

/**
 * Two audiences with the same key, which the registry refuses.
 */
enum DuplicateKeyAudience: string implements FeatureFlagAudienceInterface
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
}
