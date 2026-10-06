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
}
