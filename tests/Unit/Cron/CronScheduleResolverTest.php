<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Cron;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronScheduleResolver;

class CronScheduleResolverTest extends TestCase
{
    private CronScheduleResolver $resolver;

    private string $defaultTimezone;

    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $this->resolver = new CronScheduleResolver();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    public function testGetNextRunAtEveryMinuteReturnsNextMinute(): void
    {
        $cron = new Cron();
        $cron->setSchedule('* * * * *');
        $cron->setTimezone('UTC');
        $from = new DateTimeImmutable('2025-06-15 14:30:00');
        $next = $this->resolver->getNextRunAt($cron, $from);
        $this->assertInstanceOf(DateTimeImmutable::class, $next);
        $this->assertGreaterThan($from, $next);
        $this->assertSame('2025-06-15 14:31:00', $next->format('Y-m-d H:i:s'));
    }

    public function testGetNextRunAtFixedTimeUtc(): void
    {
        $cron = new Cron();
        $cron->setSchedule('0 9 * * *');
        $cron->setTimezone('UTC');
        $from = new DateTimeImmutable('2025-06-15 08:00:00');
        $next = $this->resolver->getNextRunAt($cron, $from);
        $this->assertSame('2025-06-15 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testGetNextRunAtFixedTimeEuropeAmsterdam(): void
    {
        $cron = new Cron();
        $cron->setSchedule('0 9 * * *');
        $cron->setTimezone('Europe/Amsterdam');
        $from = new DateTimeImmutable('2025-06-15 06:59:00+00:00'); // 08:59 Amsterdam, so next is 09:00 same day
        $next = $this->resolver->getNextRunAt($cron, $from);
        $this->assertSame('2025-06-15 07:00:00 UTC', $next->format('Y-m-d H:i:s e'));
    }

    public function testGetNextRunAtFixedTimeEuropeAmsterdamInWinter(): void
    {
        $cron = new Cron();
        $cron->setSchedule('0 9 * * *');
        $cron->setTimezone('Europe/Amsterdam');
        $from = new DateTimeImmutable('2025-12-15 07:59:00+00:00'); // 08:59 Amsterdam
        $next = $this->resolver->getNextRunAt($cron, $from);
        $this->assertSame('2025-12-15 08:00:00 UTC', $next->format('Y-m-d H:i:s e'));
    }

    /**
     * Doctrine stores next_run_at as a wall-clock time without a zone and CronRepository::findDue() compares it with
     * "now" in the default timezone; a cron in another timezone ran an hour (winter) or two (summer) late.
     */
    public function testGetNextRunAtEveryFiveMinutesInAmsterdamIsFiveMinutesLaterInTheDefaultTimezone(): void
    {
        $cron = new Cron();
        $cron->setSchedule('*/5 * * * *');
        $cron->setTimezone('Europe/Amsterdam');
        $from = new DateTimeImmutable('2025-06-15 14:31:00');
        $next = $this->resolver->getNextRunAt($cron, $from);
        $this->assertSame('2025-06-15 14:35:00 UTC', $next->format('Y-m-d H:i:s e'));
    }

    public function testGetNextRunAtIsInTheDefaultTimezoneWhateverItIs(): void
    {
        date_default_timezone_set('America/New_York');
        $cron = new Cron();
        $cron->setSchedule('0 9 * * *');
        $cron->setTimezone('Europe/Amsterdam');
        $from = new DateTimeImmutable('2025-06-15 06:59:00+00:00');
        $next = $this->resolver->getNextRunAt($cron, $from);
        $this->assertSame('2025-06-15 03:00:00 America/New_York', $next->format('Y-m-d H:i:s e'));
    }

    public function testGetNextRunAtWithNullTimezoneUsesUtc(): void
    {
        $cron = new Cron();
        $cron->setSchedule('0 12 * * *');
        $cron->setTimezone(null);
        $from = new DateTimeImmutable('2025-06-15 11:00:00');
        $next = $this->resolver->getNextRunAt($cron, $from);
        $this->assertSame('2025-06-15 12:00:00', $next->format('Y-m-d H:i:s'));
    }
}
