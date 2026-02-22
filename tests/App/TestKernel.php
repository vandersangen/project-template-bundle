<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function getConfigDir(): string
    {
        return __DIR__ . '/config';
    }
}
