<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\DependencyInjection;

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
            ->arrayNode('payment')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('api_base_url')
                        ->defaultValue('%env(PAYMENT_API_BASE_URL)%')
                        ->info('Base URL of the central payment-api (e.g. http://app.payment-api.localhost:4243)')
                    ->end()
                    ->scalarNode('api_token')
                        ->defaultValue('%env(PAYMENT_API_TOKEN)%')
                        ->info('JWT token used to authenticate against the payment-api')
                    ->end()
                    ->scalarNode('webhook_secret')
                        ->defaultValue('%env(PAYMENT_WEBHOOK_SECRET)%')
                        ->info('Shared secret validated in X-Webhook-Secret header on incoming webhooks')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('shopify')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('api_version')
                        ->defaultValue('2026-01')
                        ->info('Shopify Admin API version used for REST and GraphQL calls')
                    ->end()
                ->end()
            ->end()
            ->scalarNode('encryption_secret')
                ->defaultValue('%env(APP_SECRET)%')
                ->info('Secret used to encrypt stored third-party credentials at rest (defaults to APP_SECRET)')
            ->end()
            ->arrayNode('two_factor')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('issuer')
                        ->defaultValue('App')
                        ->info('Issuer name shown in the authenticator app for TOTP two-factor entries')
                    ->end()
                ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
