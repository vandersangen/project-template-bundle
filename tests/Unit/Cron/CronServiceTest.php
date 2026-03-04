<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Repository\CronRepository;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronScheduleResolver;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronService;

class CronServiceTest extends TestCase
{
    private CronRepository $cronRepository;
    private CronScheduleResolver $cronScheduleResolver;
    private CronService $cronService;

    protected function setUp(): void
    {
        $this->cronRepository = $this->createMock(CronRepository::class);
        $this->cronScheduleResolver = $this->createMock(CronScheduleResolver::class);
        $this->cronService = new CronService($this->cronRepository, $this->cronScheduleResolver);
    }

    public function testFindAllReturnsRepositoryFindBy(): void
    {
        $crons = [new Cron(), new Cron()];
        $this->cronRepository->expects($this->once())
            ->method('findBy')
            ->with([], ['name' => 'ASC'])
            ->willReturn($crons);
        $this->assertSame($crons, $this->cronService->findAll());
    }

    public function testFindByIdReturnsRepositoryFind(): void
    {
        $cron = new Cron();
        $this->cronRepository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($cron);
        $this->assertSame($cron, $this->cronService->findById(42));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->cronRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);
        $this->assertNull($this->cronService->findById(999));
    }

    public function testCreatePersistsCronAndSetsNextRunAt(): void
    {
        $now = new \DateTimeImmutable();
        $nextRun = $now->modify('+1 hour');
        $this->cronScheduleResolver->expects($this->once())
            ->method('getNextRunAt')
            ->with(
                $this->callback(
                    fn(Cron $c) => $c->getName() === 'Backup'
                        && $c->getCommand() === 'app:backup'
                        && $c->getSchedule() === '0 * * * *'
                    ),
                $this->anything()
            )
            ->willReturn($nextRun);
        $this->cronRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(fn(Cron $c) => $c->getName() === 'Backup'
                    && $c->getCommand() === 'app:backup'
                    && $c->getSchedule() === '0 * * * *'
                    && $c->getNextRunAt() === $nextRun
                    && $c->isEnabled() === true
                    && $c->getTimezone() === 'UTC'),
                true
            );
        $data = [
            'name' => 'Backup',
            'command' => 'app:backup',
            'schedule' => '0 * * * *',
        ];
        $cron = $this->cronService->create($data);
        $this->assertSame('Backup', $cron->getName());
        $this->assertSame('app:backup', $cron->getCommand());
        $this->assertSame($nextRun, $cron->getNextRunAt());
    }

    public function testCreateWithOptionalFields(): void
    {
        $nextRun = new \DateTimeImmutable('2025-01-02 09:00:00');
        $this->cronScheduleResolver->method('getNextRunAt')->willReturn($nextRun);
        $this->cronRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(fn(Cron $c) => $c->getCommandArguments() === ['--env' => 'prod']
                    && $c->isEnabled() === false
                    && $c->getTimezone() === 'Europe/Amsterdam'),
                true
            );
        $cron = $this->cronService->create([
            'name' => 'Job',
            'command' => 'app:run',
            'schedule' => '* * * * *',
            'commandArguments' => ['--env' => 'prod'],
            'enabled' => false,
            'timezone' => 'Europe/Amsterdam',
        ]);
        $this->assertSame(['--env' => 'prod'], $cron->getCommandArguments());
        $this->assertFalse($cron->isEnabled());
        $this->assertSame('Europe/Amsterdam', $cron->getTimezone());
    }

    public function testUpdateModifiesCronAndSaves(): void
    {
        $cron = new Cron();
        $cron->setName('Old');
        $cron->setCommand('old:cmd');
        $cron->setSchedule('0 0 * * *');
        $nextRun = new \DateTimeImmutable('2025-02-01 10:00:00');
        $this->cronScheduleResolver->expects($this->once())
            ->method('getNextRunAt')
            ->willReturn($nextRun);
        $this->cronRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(fn(Cron $c) => $c->getName() === 'New'
                    && $c->getCommand() === 'new:cmd'
                    && $c->getSchedule() === '0 10 * * *'
                    && $c->getNextRunAt() === $nextRun),
                true
            );
        $updated = $this->cronService->update($cron, [
            'name' => 'New',
            'command' => 'new:cmd',
            'schedule' => '0 10 * * *',
        ]);
        $this->assertSame('New', $updated->getName());
        $this->assertSame('new:cmd', $updated->getCommand());
        $this->assertSame($nextRun, $updated->getNextRunAt());
    }

    public function testDeleteCallsRepositoryRemove(): void
    {
        $cron = new Cron();
        $this->cronRepository->expects($this->once())
            ->method('remove')
            ->with($cron, true);
        $this->cronService->delete($cron);
    }
}
