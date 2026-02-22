<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ProjectTemplateExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );

        $loader->load('services.yaml');

        // Process configuration
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set parameters from configuration
        $container->setParameter('project_template.mailer_sender', $config['mailer_sender']);

        // Store bundle directory for routing
        $container->setParameter('project_template.bundle_dir', dirname(__DIR__));
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Configure Doctrine to recognize bundle entities
        $bundleDir = dirname(__DIR__);
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'ProjectTemplateBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/User/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\User\\Entity',
                        'alias' => 'ProjectTemplateBundle',
                    ],
                ],
            ],
        ]);
    }
}
