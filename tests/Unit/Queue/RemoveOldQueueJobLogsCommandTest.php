<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use VanDerSangen\ProjectTemplateBundle\Queue\Command\RemoveOldQueueJobLogsCommand;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;

class RemoveOldQueueJobLogsCommandTest extends TestCase
{
    public function testRemovesTheLinesOlderThanTheGivenDays(): void
    {
        $repository = $this->createMock(QueueJobLogRepository::class);
        $repository->expects($this->once())
            ->method('removeStartedBefore')
            ->with($this->callback(function (DateTimeImmutable $moment): bool {
                $expected = new DateTimeImmutable('-7 days');
                return abs($moment->getTimestamp() - $expected->getTimestamp()) < 5;
            }))
            ->willReturn(3);
        $tester = new CommandTester(new RemoveOldQueueJobLogsCommand($repository));

        $tester->execute(['--days' => '7']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Removed 3 queue_job_logs lines older than 7 days.', $tester->getDisplay());
    }

    public function testKeepsThirtyDaysByDefault(): void
    {
        $repository = $this->createMock(QueueJobLogRepository::class);
        $repository->method('removeStartedBefore')->willReturn(0);
        $tester = new CommandTester(new RemoveOldQueueJobLogsCommand($repository));

        $tester->execute([]);

        $this->assertStringContainsString('older than 30 days', $tester->getDisplay());
    }

    public function testRefusesZeroDays(): void
    {
        $repository = $this->createMock(QueueJobLogRepository::class);
        $repository->expects($this->never())->method('removeStartedBefore');
        $tester = new CommandTester(new RemoveOldQueueJobLogsCommand($repository));

        $tester->execute(['--days' => '0']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
    }
}
