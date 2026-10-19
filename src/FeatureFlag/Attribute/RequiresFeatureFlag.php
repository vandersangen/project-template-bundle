<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute;

use Attribute;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;

/**
 * This controller or action belongs to a feature flag: while the flag is off for the caller's audience, the route
 * answers 404, as if it did not exist (RequiresFeatureFlagListener).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RequiresFeatureFlag
{
    /**
     * @param FeatureFlagInterface $featureFlag The flag the route belongs to.
     */
    public function __construct(public FeatureFlagInterface $featureFlag)
    {
    }
}
