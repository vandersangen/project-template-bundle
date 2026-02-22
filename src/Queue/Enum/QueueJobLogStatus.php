<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Enum;

enum QueueJobLogStatus: string
{
    case STARTED = 'started';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
