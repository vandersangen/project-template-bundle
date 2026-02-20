<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('project_template');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('mailer_sender')
                    ->defaultValue('noreply@example.com')
                    ->info('Default sender email address for the mailer service')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
