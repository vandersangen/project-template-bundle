<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Cron
{
    public function __construct(
        public readonly string $name,
        public readonly string $schedule,
    ) {
    }
}
