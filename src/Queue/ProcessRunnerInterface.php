<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue;

use Symfony\Component\Process\Process;

interface ProcessRunnerInterface
{
    public function run(Process $process): bool;
}
