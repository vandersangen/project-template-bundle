<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use VanDerSangen\ProjectTemplateBundle\Cron\Command\ScheduleCronCommand;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Repository\CronRepository;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronScheduleResolver;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\RunCronMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\Service\QueueService;

class ScheduleCronCommandTest extends TestCase
{
    private CronRepository $cronRepository;
    private CronScheduleResolver $cronScheduleResolver;
    private QueueService $queueService;
    private ScheduleCronCommand $command;

    protected function setUp(): void
    {
        $this->cronRepository = $this->createMock(CronRepository::class);
        $this->cronScheduleResolver = $this->createMock(CronScheduleResolver::class);
        $this->queueService = $this->createMock(QueueService::class);
        $this->command = new ScheduleCronCommand(
            $this->cronRepository,
            $this->cronScheduleResolver,
            $this->queueService
        );
    }

    public function testExecuteWithNoDueCronsDispatchesNothing(): void
    {
        $this->cronRepository->expects($this->once())
            ->method('findDue')
            ->with($this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([]);
        $this->queueService->expects($this->never())->method('dispatch');
        $this->cronRepository->expects($this->never())->method('save');
        $exitCode = $this->command->run(new ArrayInput([]), new BufferedOutput());
        $this->assertSame(ScheduleCronCommand::SUCCESS, $exitCode);
    }

    public function testExecuteWithDueCronsDispatchesAndUpdatesEach(): void
    {
        $now = new \DateTimeImmutable();
        $next1 = $now->modify('+1 hour');
        $next2 = $now->modify('+2 hours');
        $cron1 = new Cron();
        $cron1->setName('Job1');
        $cron1->setCommand('app:one');
        $cron1->setSchedule('* * * * *');
        $this->setCronId($cron1, 10);
        $cron2 = new Cron();
        $cron2->setName('Job2');
        $cron2->setCommand('app:two');
        $cron2->setSchedule('0 * * * *');
        $this->setCronId($cron2, 20);
        $this->cronRepository->expects($this->once())
            ->method('findDue')
            ->willReturn([$cron1, $cron2]);
        $this->cronScheduleResolver->expects($this->exactly(2))
            ->method('getNextRunAt')
            ->willReturnOnConsecutiveCalls($next1, $next2);
        $this->queueService->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(fn($msg) => $msg instanceof RunCronMessage
                && ($msg->getCronId() === 10 || $msg->getCronId() === 20)))
            ->willReturnCallback(fn (RunCronMessage $msg) => new \Symfony\Component\Messenger\Envelope($msg));
        $this->cronRepository->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(Cron::class), true);
        $exitCode = $this->command->run(new ArrayInput([]), new BufferedOutput());
        $this->assertSame(ScheduleCronCommand::SUCCESS, $exitCode);
        $this->assertSame($next1, $cron1->getNextRunAt());
        $this->assertSame($next2, $cron2->getNextRunAt());
    }

    private function setCronId(Cron $cron, int $id): void
    {
        $ref = new \ReflectionClass($cron);
        $prop = $ref->getProperty('id');
        $prop->setValue($cron, $id);
    }
}
