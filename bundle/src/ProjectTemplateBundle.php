<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ProjectTemplateBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
