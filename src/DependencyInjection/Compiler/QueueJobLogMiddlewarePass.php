<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use VanDerSangen\ProjectTemplateBundle\Queue\Middleware\QueueJobLogMiddleware;

/**
 * Prepends QueueJobLogMiddleware to the default Messenger bus so queue jobs are logged
 * regardless of app config merge order.
 */
class QueueJobLogMiddlewarePass implements CompilerPassInterface
{
    private const string DEFAULT_BUS_ID = 'messenger.bus.default';

    public function process(ContainerBuilder $container): void
    {
        $param = self::DEFAULT_BUS_ID . '.middleware';

        if (!$container->hasParameter($param)) {
            return;
        }

        $middleware = $container->getParameter($param);
        if (!is_array($middleware)) {
            return;
        }

        foreach ($middleware as $item) {
            if (is_array($item) && ($item['id'] ?? null) === QueueJobLogMiddleware::class) {
                return;
            }
        }

        $container->setParameter($param, array_merge([['id' => QueueJobLogMiddleware::class]], $middleware));
    }
}
