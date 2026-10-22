<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute;

use Attribute;

/**
 * This controller or action belongs to a feature flag: while the flag is off for the caller, the route answers 404,
 * as if it did not exist (RequiresFeatureFlagListener). With more than one attribute every flag must be on.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class RequiresFeatureFlag
{
    /**
     * @param string $cid The cid of the flag the route belongs to.
     */
    public function __construct(public string $cid)
    {
    }
}
