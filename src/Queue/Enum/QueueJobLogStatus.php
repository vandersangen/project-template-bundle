<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Enum;

/**
 * Queued: the message only went onto a transport here; a worker logs its own line when it handles it.
 */
enum QueueJobLogStatus: string
{
    case QUEUED = 'queued';
    case STARTED = 'started';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
