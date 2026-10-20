<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test app as a project that configures no feature flag audiences.
 *
 * The audiences of the test config cannot be taken away by loading another config (the lists of two configs are
 * merged), so this kernel, as a compiler pass, empties the parameter after the extensions have loaded. The resolver
 * alias of the test app stays; without audiences the bundle does not use it.
 */
class WithoutFeatureFlagsTestKernel extends TestKernel implements CompilerPassInterface
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('project_template', [
                'feature_flags' => ['audiences' => []],
            ]);
        });
    }

    public function process(ContainerBuilder $container): void
    {
        $container->setParameter('project_template.feature_flags.audiences', []);
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '/without_feature_flags';
    }

    public function getBuildDir(): string
    {
        return parent::getBuildDir() . '/without_feature_flags';
    }
}
