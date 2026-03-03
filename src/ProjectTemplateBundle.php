<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use VanDerSangen\ProjectTemplateBundle\DependencyInjection\Compiler\QueueJobLogMiddlewarePass;

class ProjectTemplateBundle extends Bundle
{
    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    #[\Override]
    public function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new QueueJobLogMiddlewarePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 50);
    }
}
