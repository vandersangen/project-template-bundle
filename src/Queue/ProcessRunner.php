<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue;

use Symfony\Component\Process\Process;

class ProcessRunner implements ProcessRunnerInterface
{
    private const float REPORT_EVERY_SECONDS = 1.0;

    private const int POLL_MICROSECONDS = 100_000;

    /**
     * Without $onOutput a plain run. With it the output is reported on a clock rather than per chunk, so a command
     * that prints a line and then works in silence still shows that line.
     *
     * @param Process       $process  The process to run.
     * @param callable|null $onOutput Called with all output so far.
     *
     * @return bool
     */
    public function run(Process $process, ?callable $onOutput = null): bool
    {
        if ($onOutput === null) {
            $process->run();

            return $process->isSuccessful();
        }

        $output = '';
        $reported = '';
        $process->start(static function (string $type, string $chunk) use (&$output): void {
            $output .= $chunk;
        });

        $lastReport = microtime(true);
        while ($process->isRunning()) {
            $process->checkTimeout();
            usleep(self::POLL_MICROSECONDS);
            if ($output !== $reported && microtime(true) - $lastReport >= self::REPORT_EVERY_SECONDS) {
                $onOutput($output);
                $reported = $output;
                $lastReport = microtime(true);
            }
        }

        // Reads what is still in the pipes once the process has ended.
        $process->wait();
        $onOutput($output);

        return $process->isSuccessful();
    }
}
