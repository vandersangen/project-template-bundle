<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test app as a project that configures no feature flags: the flags and audiences of the test config are
 * taken away again.
 */
class WithoutFeatureFlagsTestKernel extends TestKernel
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('project_template', [
                'feature_flags' => ['flags' => null, 'audiences' => null],
            ]);
        });
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
