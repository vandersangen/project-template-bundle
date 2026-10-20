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
     * @param callable|null $onOutput Called with the output so far (stdout and stderr, as they came), at most as often
     *                                as the interval for its size allows (1, 5 or 15 seconds) while the process runs
     *                                and once when it has ended. Past the maximum the start is left out, with a marker.
     *
     * @return bool Whether the process succeeded.
     */
    public function run(Process $process, ?callable $onOutput = null): bool;
}
