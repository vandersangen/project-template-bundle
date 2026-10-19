<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

/**
 * A backed enum that is no feature flag and no audience.
 */
enum EnumWithoutInterface: string
{
    case SOMETHING = 'something';
}
