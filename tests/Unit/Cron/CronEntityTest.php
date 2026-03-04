<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Cron;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;

class CronEntityTest extends TestCase
{
    private Cron $cron;

    protected function setUp(): void
    {
        $this->cron = new Cron();
    }

    public function testNewCronHasNullId(): void
    {
        $this->assertNull($this->cron->getId());
    }

    public function testSetAndGetName(): void
    {
        $this->cron->setName('Daily backup');
        $this->assertSame('Daily backup', $this->cron->getName());
    }

    public function testSetAndGetCommand(): void
    {
        $this->cron->setCommand('app:backup');
        $this->assertSame('app:backup', $this->cron->getCommand());
    }

    public function testSetAndGetCommandArguments(): void
    {
        $args = ['--env' => 'prod', 'key'];
        $this->cron->setCommandArguments($args);
        $this->assertSame($args, $this->cron->getCommandArguments());
    }

    public function testSetAndGetCommandArgumentsNull(): void
    {
        $this->cron->setCommandArguments(null);
        $this->assertNull($this->cron->getCommandArguments());
    }

    public function testSetAndGetSchedule(): void
    {
        $this->cron->setSchedule('0 9 * * *');
        $this->assertSame('0 9 * * *', $this->cron->getSchedule());
    }

    public function testDefaultEnabledIsTrue(): void
    {
        $this->assertTrue($this->cron->isEnabled());
    }

    public function testSetAndGetEnabled(): void
    {
        $this->cron->setEnabled(false);
        $this->assertFalse($this->cron->isEnabled());
    }

    public function testSetAndGetLastRunAt(): void
    {
        $at = new DateTimeImmutable('2025-01-15 10:00:00');
        $this->cron->setLastRunAt($at);
        $this->assertSame($at, $this->cron->getLastRunAt());
    }

    public function testLastRunAtDefaultIsNull(): void
    {
        $this->assertNull($this->cron->getLastRunAt());
    }

    public function testSetAndGetNextRunAt(): void
    {
        $at = new DateTimeImmutable('2025-01-16 09:00:00');
        $this->cron->setNextRunAt($at);
        $this->assertSame($at, $this->cron->getNextRunAt());
    }

    public function testNextRunAtDefaultIsNull(): void
    {
        $this->assertNull($this->cron->getNextRunAt());
    }

    public function testDefaultTimezoneIsUtc(): void
    {
        $this->assertSame('UTC', $this->cron->getTimezone());
    }

    public function testSetAndGetTimezone(): void
    {
        $this->cron->setTimezone('Europe/Amsterdam');
        $this->assertSame('Europe/Amsterdam', $this->cron->getTimezone());
    }

    public function testSetTimezoneNull(): void
    {
        $this->cron->setTimezone(null);
        $this->assertNull($this->cron->getTimezone());
    }

    public function testToArrayWithNullDates(): void
    {
        $this->cron->setName('Test');
        $this->cron->setCommand('list');
        $this->cron->setSchedule('* * * * *');
        $array = $this->cron->toArray();
        $this->assertNull($array['id']);
        $this->assertSame('Test', $array['name']);
        $this->assertSame('list', $array['command']);
        $this->assertSame('* * * * *', $array['schedule']);
        $this->assertTrue($array['enabled']);
        $this->assertNull($array['lastRunAt']);
        $this->assertNull($array['nextRunAt']);
        $this->assertSame('UTC', $array['timezone']);
    }

    public function testToArrayWithDatesAndCommandArguments(): void
    {
        $last = new DateTimeImmutable('2025-01-01 08:00:00');
        $next = new DateTimeImmutable('2025-01-02 08:00:00');
        $this->cron->setName('Job');
        $this->cron->setCommand('app:run');
        $this->cron->setCommandArguments(['--foo' => 'bar']);
        $this->cron->setSchedule('0 8 * * *');
        $this->cron->setLastRunAt($last);
        $this->cron->setNextRunAt($next);
        $array = $this->cron->toArray();
        $this->assertSame('Job', $array['name']);
        $this->assertSame(['--foo' => 'bar'], $array['commandArguments']);
        $this->assertSame($last->format('c'), $array['lastRunAt']);
        $this->assertSame($next->format('c'), $array['nextRunAt']);
    }

    public function testSetterReturnsSelf(): void
    {
        $this->assertSame($this->cron, $this->cron->setName('x'));
        $this->assertSame($this->cron, $this->cron->setCommand('y'));
        $this->assertSame($this->cron, $this->cron->setSchedule('* * * * *'));
        $this->assertSame($this->cron, $this->cron->setEnabled(true));
        $this->assertSame($this->cron, $this->cron->setTimezone('UTC'));
        $this->assertSame($this->cron, $this->cron->setLastRunAt(null));
        $this->assertSame($this->cron, $this->cron->setNextRunAt(null));
    }
}
