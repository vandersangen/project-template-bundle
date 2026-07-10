<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\DependencyInjection;

use Monolog\Logger as MonologLogger;
use Sentry\Monolog\Handler as SentryMonologHandler;
use Sentry\SentryBundle\Monolog\LogsHandler as SentryLogsHandler;
use Sentry\State\HubInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class ProjectTemplateExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );

        $loader->load('services.yaml');

        if ($container->hasExtension('sentry')) {
            $loader->load('sentry_services.yaml');
        }

        if (class_exists(SentryMonologHandler::class) && class_exists(SentryLogsHandler::class)) {
            $handlerDef = new Definition(SentryMonologHandler::class, [
                new Reference(HubInterface::class),
                MonologLogger::ERROR,
                true,
                false,
            ]);
            $handlerDef->setPublic(false);
            $container->setDefinition('project_template.sentry.monolog_handler', $handlerDef);

            $logsDef = new Definition(SentryLogsHandler::class, [MonologLogger::INFO]);
            $logsDef->setPublic(false);
            $container->setDefinition('project_template.sentry.monolog_logs_handler', $logsDef);
        }

        // Process configuration
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set parameters from configuration
        $container->setParameter('project_template.mailer_sender', $config['mailer_sender']);

        // Payment module parameters
        $container->setParameter('project_template.payment.api_base_url', $config['payment']['api_base_url']);
        $container->setParameter('project_template.payment.api_token', $config['payment']['api_token']);
        $container->setParameter('project_template.payment.webhook_secret', $config['payment']['webhook_secret']);

        // Shopify module parameters
        $container->setParameter('project_template.shopify.api_version', $config['shopify']['api_version']);

        // Shared credential encryption secret
        $container->setParameter('project_template.encryption_secret', $config['encryption_secret']);

        // Store bundle directory for routing
        $container->setParameter('project_template.bundle_dir', dirname(__DIR__));
        $container->setParameter('project_template.bundle_root', dirname(__DIR__, 2));
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
                    'ProjectTemplateBundleMail' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/Mail/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\Mail\\Entity',
                        'alias' => 'ProjectTemplateBundleMail',
                    ],
                    'ProjectTemplateBundleQueue' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/Queue/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\Queue\\Entity',
                        'alias' => 'ProjectTemplateBundleQueue',
                    ],
                    'ProjectTemplateBundleCron' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/Cron/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\Cron\\Entity',
                        'alias' => 'ProjectTemplateBundleCron',
                    ],
                    'ProjectTemplateBundleSuperAdmin' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/SuperAdmin/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\SuperAdmin\\Entity',
                        'alias' => 'ProjectTemplateBundleSuperAdmin',
                    ],
                    'ProjectTemplateBundleTenant' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/Tenant/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\Tenant\\Entity',
                        'alias' => 'ProjectTemplateBundleTenant',
                    ],
                    'ProjectTemplateBundlePayment' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/Payment/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\Payment\\Entity',
                        'alias' => 'ProjectTemplateBundlePayment',
                    ],
                    'ProjectTemplateBundleInvoice' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/Invoice/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\Invoice\\Entity',
                        'alias' => 'ProjectTemplateBundleInvoice',
                    ],
                    'ProjectTemplateBundleShopify' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $bundleDir . '/Shopify/Entity',
                        'prefix' => 'VanDerSangen\\ProjectTemplateBundle\\Shopify\\Entity',
                        'alias' => 'ProjectTemplateBundleShopify',
                    ],
                ],
            ],
        ]);

        // Register bundle migrations so consuming applications can execute them
        $bundleRootDir = dirname($bundleDir);
        $container->prependExtensionConfig('twig', [
            'paths' => [$bundleRootDir . '/Resources/views' => 'ProjectTemplateBundle'],
        ]);
        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'DoctrineMigrations\\ProjectTemplateBundle' => $bundleRootDir . '/migrations',
            ],
        ]);

        // Auto-configure Sentry monolog handlers when both SentryBundle and MonologBundle are registered
        if ($container->hasExtension('sentry') && $container->hasExtension('monolog')) {
            $container->prependExtensionConfig('sentry', [
                'options' => ['enable_logs' => true],
            ]);
            $container->prependExtensionConfig('monolog', [
                'handlers' => [
                    'sentry' => [
                        'type' => 'service',
                        'id'   => 'project_template.sentry.monolog_handler',
                    ],
                    'sentry_logs' => [
                        'type'     => 'service',
                        'id'       => 'project_template.sentry.monolog_logs_handler',
                        'channels' => ['app', 'php', 'doctrine', 'request'],
                    ],
                ],
            ]);
        }

        // Messenger: failed transport only (do not prepend async -
        // in CI async may not be defined yet, causing "Undefined array key dsn").
        // App must set failure_transport: failed on their async transport.
        // QueueJobLogMiddleware is also added via QueueJobLogMiddlewarePass.
        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    'failed' => ['dsn' => '%env(messenger_failed_dsn:MESSENGER_TRANSPORT_DSN)%'],
                ],
                'buses' => [
                    'messenger.bus.default' => [
                        'middleware' => [
                            \VanDerSangen\ProjectTemplateBundle\Queue\Middleware\QueueJobLogMiddleware::class,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
