<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use VanDerSangen\ProjectTemplateBundle\Queue\ProcessRunner;

class ProcessRunnerTest extends TestCase
{
    public function testWithoutCallbackItJustRuns(): void
    {
        $this->assertTrue((new ProcessRunner())->run(new Process([PHP_BINARY, '-r', 'echo 1;'])));
        $this->assertFalse((new ProcessRunner())->run(new Process([PHP_BINARY, '-r', 'exit(3);'])));
    }

    public function testOutputIsReportedWhileTheProcessRunsAndOnceAtTheEnd(): void
    {
        $reports = [];
        $script = 'echo "first\n"; fwrite(STDERR, "error\n"); sleep(2); echo "last\n";';
        $process = new Process([PHP_BINARY, '-r', $script]);

        $succeeded = (new ProcessRunner())->run($process, function (string $output) use (&$reports): void {
            $reports[] = $output;
        });

        $this->assertTrue($succeeded);
        $this->assertGreaterThanOrEqual(2, count($reports));
        $this->assertSame("first\nerror\n", $reports[0], 'A line printed before a silence is reported during it.');
        $this->assertSame("first\nerror\nlast\n", $reports[array_key_last($reports)]);
    }

    public function testOutputPastTheLimitIsReportedCutWithAMarker(): void
    {
        $reports = [];
        $script = 'for ($i = 0; $i < 30; $i++) { echo str_repeat("é", 50), "|", $i, "\n"; } echo "einde\n";';
        $process = new Process([PHP_BINARY, '-r', $script]);

        $succeeded = (new ProcessRunner(1000))->run($process, function (string $output) use (&$reports): void {
            $reports[] = $output;
        });

        $final = $reports[array_key_last($reports)];
        $marker = "… (start left out)\n";
        $this->assertTrue($succeeded);
        $this->assertStringStartsWith($marker, $final);
        $this->assertStringEndsWith("29\neinde\n", $final);
        $this->assertLessThanOrEqual(1000 + strlen($marker), strlen($final));
        $this->assertTrue(mb_check_encoding($final, 'UTF-8'), 'The cut falls between characters.');
    }

    public function testSmallOutputIsReportedUncutAndWithoutMarker(): void
    {
        $reports = [];
        $process = new Process([PHP_BINARY, '-r', 'echo "small\n";']);

        (new ProcessRunner(1000))->run($process, function (string $output) use (&$reports): void {
            $reports[] = $output;
        });

        $this->assertSame("small\n", $reports[array_key_last($reports)]);
    }

    public function testReportIntervalGrowsWithTheSizeOfTheOutput(): void
    {
        $method = new \ReflectionMethod(ProcessRunner::class, 'reportIntervalSecondsFor');
        $runner = new ProcessRunner();

        $this->assertSame(1.0, $method->invoke($runner, 65_536));
        $this->assertSame(5.0, $method->invoke($runner, 65_537));
        $this->assertSame(5.0, $method->invoke($runner, 262_144));
        $this->assertSame(15.0, $method->invoke($runner, 262_145));
    }
}
