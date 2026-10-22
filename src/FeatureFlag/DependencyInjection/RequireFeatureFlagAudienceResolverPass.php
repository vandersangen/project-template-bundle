<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\DependencyInjection;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;

/**
 * A project that configures audiences must say whose a signed-in user is: without a resolver the container does not
 * build. A project without audiences needs none.
 */
final class RequireFeatureFlagAudienceResolverPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container The container being built.
     *
     * @throws LogicException When audiences are configured and no service or alias for the resolver exists.
     */
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('project_template.feature_flags.audiences') === []) {
            return;
        }

        if ($container->has(FeatureFlagAudienceResolverInterface::class)) {
            return;
        }

        throw new LogicException(sprintf(
            '%s is set, so a service for %s is required (%s).',
            'project_template.feature_flags.audiences',
            'FeatureFlagAudienceResolverInterface',
            'docs/feature-flags.md',
        ));
    }
}
