<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Repository\CronRepository;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronScheduleResolver;
use VanDerSangen\ProjectTemplateBundle\Queue\Handler\AsyncMessageHandlerInterface;
use VanDerSangen\ProjectTemplateBundle\Queue\Handler\RunCronMessageHandler;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\RunCronMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\Entity\QueueJobLog;
use VanDerSangen\ProjectTemplateBundle\Queue\ProcessRunnerInterface;
use VanDerSangen\ProjectTemplateBundle\Queue\QueueJobLogContext;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;
use Symfony\Component\HttpKernel\KernelInterface;

class RunCronMessageHandlerTest extends TestCase
{
    private CronRepository $cronRepository;
    private CronScheduleResolver $cronScheduleResolver;
    private KernelInterface $kernel;
    private ProcessRunnerInterface $processRunner;
    private QueueJobLogContext $queueJobLogContext;
    private QueueJobLogRepository $queueJobLogRepository;
    private RunCronMessageHandler $handler;

    protected function setUp(): void
    {
        $this->cronRepository = $this->createMock(CronRepository::class);
        $this->cronScheduleResolver = $this->createMock(CronScheduleResolver::class);
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->processRunner = $this->createMock(ProcessRunnerInterface::class);
        $this->queueJobLogContext = new QueueJobLogContext();
        $this->queueJobLogRepository = $this->createMock(QueueJobLogRepository::class);
        $this->handler = new RunCronMessageHandler(
            $this->cronRepository,
            $this->cronScheduleResolver,
            $this->kernel,
            $this->processRunner,
            $this->queueJobLogContext,
            $this->queueJobLogRepository
        );
    }

    public function testImplementsAsyncMessageHandlerInterface(): void
    {
        $this->assertInstanceOf(AsyncMessageHandlerInterface::class, $this->handler);
    }

    public function testInvokeThrowsWhenCronNotFound(): void
    {
        $this->cronRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cron with ID 999 not found.');
        ($this->handler)(new RunCronMessage(999));
    }

    public function testInvokeRunsProcessAndUpdatesCronOnSuccess(): void
    {
        $cron = new Cron();
        $cron->setName('Test');
        $cron->setCommand('list');
        $cron->setSchedule('* * * * *');
        $this->cronRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($cron);
        $this->kernel->expects($this->once())
            ->method('getProjectDir')
            ->willReturn('/app');
        $this->processRunner->expects($this->once())
            ->method('run')
            ->with($this->anything())
            ->willReturn(true);
        $nextRun = new DateTimeImmutable('2025-07-01 10:00:00');
        $this->cronScheduleResolver->expects($this->once())
            ->method('getNextRunAt')
            ->with($cron, $this->isInstanceOf(DateTimeImmutable::class))
            ->willReturn($nextRun);
        $this->cronRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(fn(Cron $c) => $c->getLastRunAt() !== null && $c->getNextRunAt() === $nextRun),
                true
            );
        ($this->handler)(new RunCronMessage(1));
        $this->assertNotNull($cron->getLastRunAt());
        $this->assertSame($nextRun, $cron->getNextRunAt());
    }

    public function testInvokeThrowsWhenProcessFails(): void
    {
        $cron = new Cron();
        $cron->setCommand('list');
        $cron->setSchedule('* * * * *');
        $this->cronRepository->method('find')->with(2)->willReturn($cron);
        $this->kernel->method('getProjectDir')->willReturn('/app');
        $this->processRunner->expects($this->once())
            ->method('run')
            ->willReturn(false);
        $this->cronScheduleResolver->expects($this->never())->method('getNextRunAt');
        $this->cronRepository->expects($this->never())->method('save');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cron command failed:');
        ($this->handler)(new RunCronMessage(2));
    }

    public function testInvokeBuildsCommandLineWithPositionalAndNamedArguments(): void
    {
        $cron = new Cron();
        $cron->setCommand('app:run');
        $cron->setSchedule('* * * * *');
        $cron->setCommandArguments([0 => 'pos1', 'opt' => 'val']);
        $this->cronRepository->method('find')->with(3)->willReturn($cron);
        $this->kernel->method('getProjectDir')->willReturn('/project');
        $capturedProcess = null;
        $this->processRunner->expects($this->once())
            ->method('run')
            ->willReturnCallback(function ($process) use (&$capturedProcess) {
                $capturedProcess = $process;
                return true;
            });
        $this->cronScheduleResolver->method('getNextRunAt')->willReturn(new DateTimeImmutable());
        $this->cronRepository->expects($this->once())->method('save');
        ($this->handler)(new RunCronMessage(3));
        $this->assertNotNull($capturedProcess);
        $cmd = $capturedProcess->getCommandLine();
        $this->assertStringContainsString('pos1', $cmd);
        $this->assertStringContainsString('--opt=val', $cmd);
        $this->assertStringContainsString('/project/bin/console', $cmd);
        $this->assertStringContainsString('app:run', $cmd);
    }

    public function testOutputGoesIntoTheLogLineOfTheMessageWhileItRuns(): void
    {
        $cron = new Cron();
        $cron->setCommand('list');
        $cron->setSchedule('* * * * *');
        $this->cronRepository->method('find')->willReturn($cron);
        $this->kernel->method('getProjectDir')->willReturn('/app');
        $this->cronScheduleResolver->method('getNextRunAt')->willReturn(new DateTimeImmutable());
        $queueJobLog = new QueueJobLog();
        $idProperty = new \ReflectionProperty(QueueJobLog::class, 'id');
        $idProperty->setValue($queueJobLog, 12);
        $this->queueJobLogContext->enter($queueJobLog);
        $this->processRunner->method('run')
            ->willReturnCallback(function ($process, ?callable $onOutput) {
                $onOutput('line 1');
                $onOutput("line 1\nline 2");
                return true;
            });
        $saved = [];
        $this->queueJobLogRepository->expects($this->exactly(2))
            ->method('saveOutputSoFar')
            ->willReturnCallback(function (int $id, string $stdout) use (&$saved) {
                $saved[] = [$id, $stdout];
            });
        ($this->handler)(new RunCronMessage(1));
        $this->assertSame([[12, 'line 1'], [12, "line 1\nline 2"]], $saved);
        $this->assertSame("line 1\nline 2", $queueJobLog->getStdout());
    }

    public function testOutputIsNotWrittenOutsideTheMiddleware(): void
    {
        $cron = new Cron();
        $cron->setCommand('list');
        $cron->setSchedule('* * * * *');
        $this->cronRepository->method('find')->willReturn($cron);
        $this->kernel->method('getProjectDir')->willReturn('/app');
        $this->cronScheduleResolver->method('getNextRunAt')->willReturn(new DateTimeImmutable());
        $this->processRunner->method('run')
            ->willReturnCallback(function ($process, ?callable $onOutput) {
                $onOutput('line 1');
                return true;
            });
        $this->queueJobLogRepository->expects($this->never())->method('saveOutputSoFar');
        ($this->handler)(new RunCronMessage(1));
    }
}
