<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue;

use Symfony\Component\Process\Process;

class ProcessRunner implements ProcessRunnerInterface
{
    public function run(Process $process): bool
    {
        $process->run();
        return $process->isSuccessful();
    }
}
