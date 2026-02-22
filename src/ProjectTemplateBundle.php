<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ProjectTemplateBundle extends Bundle
{
    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
