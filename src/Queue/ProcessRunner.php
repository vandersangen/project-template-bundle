<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

class ProcessRunner implements ProcessRunnerInterface
{
    private const string START_LEFT_OUT_MARKER = "… (start left out)\n";

    private const int POLL_MICROSECONDS = 100_000;

    private const int SMALL_OUTPUT_BYTES = 65_536;

    private const int MEDIUM_OUTPUT_BYTES = 262_144;

    public function __construct(
        #[Autowire(param: 'project_template.queue.max_cron_output_bytes')]
        private readonly int $maxOutputBytes = 1048576,
    ) {
    }

    /**
     * Without $onOutput a plain run. With it the output is reported on a clock rather than per chunk, so a command
     * that prints a line and then works in silence still shows that line. The clock slows down as the output grows
     * (1 second up to 64 kB, 5 seconds up to 256 kB, then 15 seconds), because every report writes all output so far.
     *
     * The output kept here is bounded: past twice the maximum only the last maximum bytes stay in memory. Symfony's
     * Process keeps its own copy in a php://temp stream (PHP spills it to disk past 2 MB), which this does not change.
     *
     * @param Process       $process  The process to run.
     * @param callable|null $onOutput Called with the output so far, cut to the last maximum bytes and prefixed with a
     *                                marker when the start was left out.
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
        $startLeftOut = false;
        $changed = false;
        $process->start(function (string $type, string $chunk) use (&$output, &$startLeftOut, &$changed): void {
            $output .= $chunk;
            $changed = true;

            if (strlen($output) > 2 * $this->maxOutputBytes) {
                $output = $this->lastBytesOf($output);
                $startLeftOut = true;
            }
        });

        $lastReport = microtime(true);
        while ($process->isRunning()) {
            $process->checkTimeout();
            usleep(self::POLL_MICROSECONDS);

            if ($changed && microtime(true) - $lastReport >= $this->reportIntervalSecondsFor(strlen($output))) {
                $onOutput($this->reportableOutput($output, $startLeftOut));
                $changed = false;
                $lastReport = microtime(true);
            }
        }

        // Reads what is still in the pipes once the process has ended.
        $process->wait();
        $onOutput($this->reportableOutput($output, $startLeftOut));

        return $process->isSuccessful();
    }

    private function reportIntervalSecondsFor(int $outputBytes): float
    {
        if ($outputBytes <= self::SMALL_OUTPUT_BYTES) {
            return 1.0;
        }

        if ($outputBytes <= self::MEDIUM_OUTPUT_BYTES) {
            return 5.0;
        }

        return 15.0;
    }

    private function reportableOutput(string $output, bool $startLeftOut): string
    {
        if (strlen($output) > $this->maxOutputBytes) {
            return self::START_LEFT_OUT_MARKER . $this->lastBytesOf($output);
        }

        return $startLeftOut ? self::START_LEFT_OUT_MARKER . $output : $output;
    }

    private function lastBytesOf(string $output): string
    {
        // Cut on bytes, cheap for large output; mb_strcut moves the cut to the start of a character.
        return mb_strcut($output, strlen($output) - $this->maxOutputBytes, null, 'UTF-8');
    }
}
