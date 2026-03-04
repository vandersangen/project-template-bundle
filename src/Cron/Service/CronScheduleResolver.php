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

        return \DateTimeImmutable::createFromMutable($next);
    }
}
