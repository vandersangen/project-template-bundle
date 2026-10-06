<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue;

use Symfony\Component\Process\Process;

interface ProcessRunnerInterface
{
    /**
     * Runs the process to the end.
     *
     * @param Process       $process  The process to run.
     * @param callable|null $onOutput Called with all output so far (stdout and stderr, as they came), at most about
     *                                once a second while the process runs and once when it has ended.
     *
     * @return bool Whether the process succeeded.
     */
    public function run(Process $process, ?callable $onOutput = null): bool;
}
