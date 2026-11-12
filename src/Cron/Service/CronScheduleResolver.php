<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Service;

use DateTime;
use DateTimeZone;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use Cron\CronExpression;

class CronScheduleResolver
{
    public function getNextRunAt(Cron $cron, \DateTimeImmutable $from): \DateTimeImmutable
    {
        $timezone = $cron->getTimezone() ?? 'UTC';
        $cronExpression = CronExpression::factory($cron->getSchedule());
        $fromDateTime = DateTime::createFromImmutable($from);
        $fromDateTime->setTimezone(new DateTimeZone($timezone));
        $next = $cronExpression->getNextRunDate($fromDateTime, 0, false);

        // Worked out in the timezone of the cron, so "0 3 * * *" stays 03:00 there across daylight saving time. Handed
        // back in the default timezone: Doctrine stores next_run_at as a wall-clock time without a zone, and
        // CronRepository::findDue() compares it with "now" in the default timezone.
        return \DateTimeImmutable::createFromMutable($next)
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
}
