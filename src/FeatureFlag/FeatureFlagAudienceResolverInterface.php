<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag;

/**
 * Decides whose the current request is, so that the switch of that audience counts.
 *
 * The bundle's default (SingleAudienceResolver) gives the first configured audience; a project with more than one
 * audience aliases this interface to its own resolver.
 */
interface FeatureFlagAudienceResolverInterface
{
    /**
     * The audience of the caller of the current request.
     *
     * @return FeatureFlagAudienceInterface
     */
    public function resolveAudienceOfCurrentRequest(): FeatureFlagAudienceInterface;
}
