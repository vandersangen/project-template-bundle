<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service;

use LogicException;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;

/**
 * The default resolver, for a project with one audience: every request is of the first configured audience.
 *
 * A project with more audiences aliases FeatureFlagAudienceResolverInterface to its own resolver.
 */
final readonly class SingleAudienceResolver implements FeatureFlagAudienceResolverInterface
{
    public function __construct(private FeatureFlagRegistry $featureFlagRegistry)
    {
    }

    /**
     * @return FeatureFlagAudienceInterface
     *
     * @throws LogicException When the project configured no audiences.
     */
    public function resolveAudienceOfCurrentRequest(): FeatureFlagAudienceInterface
    {
        return $this->featureFlagRegistry->audiences()[0]
            ?? throw new LogicException('Configure project_template.feature_flags.audiences.');
    }
}
