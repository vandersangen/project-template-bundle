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
            ->arrayNode('queue')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('max_cron_output_bytes')
                        ->defaultValue(1048576)
                        ->min(1024)
                        ->info('Most cron output kept in queue_job_logs; beyond it the start is left out')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('feature_flags')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('audiences')
                        ->info('Who a flag can be switched for. Key: a-z, then up to 19 of a-z, 0-9, _; never changed.')
                        ->useAttributeAsKey('key')
                        ->normalizeKeys(false)
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('label')->isRequired()->cannotBeEmpty()->end()
                                ->booleanNode('guest')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(self::hasInvalidAudienceKey(...))
                            ->thenInvalid('An audience key is a-z, then up to 19 of a-z, 0-9 and _: %s')
                        ->end()
                        ->validate()
                            ->ifTrue(self::hasMoreThanOneGuestAudience(...))
                            ->thenInvalid('At most one audience has guest: true: %s')
                        ->end()
                    ->end()
                ->end()
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

    /**
     * @param array<array-key, mixed> $audiences The audiences, by key.
     *
     * @return bool Whether a key does not start with a-z or is not at most 20 characters of a-z, 0-9 and _ (the
     *              length of its column). The leading letter keeps PHP from turning a key such as 2026 into an int.
     */
    private static function hasInvalidAudienceKey(array $audiences): bool
    {
        foreach (array_keys($audiences) as $key) {
            if (preg_match('/^[a-z][a-z0-9_]{0,19}$/', (string) $key) !== 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $audiences The audiences, by key.
     *
     * @return bool Whether more than one audience is the audience of a visitor without login.
     */
    private static function hasMoreThanOneGuestAudience(array $audiences): bool
    {
        $guestAudiences = array_filter(
            $audiences,
            self::isGuestAudience(...),
        );

        return count($guestAudiences) > 1;
    }

    /**
     * @param mixed $audience The settings of one audience.
     *
     * @return bool
     */
    private static function isGuestAudience(mixed $audience): bool
    {
        return is_array($audience) && ($audience['guest'] ?? false) === true;
    }
}
