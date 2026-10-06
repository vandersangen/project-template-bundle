<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue;

use VanDerSangen\ProjectTemplateBundle\Queue\Entity\QueueJobLog;

/**
 * The queue_job_logs line of the message being handled right now, so a handler can write to its own line while it
 * runs (RunCronMessageHandler streams the output of the cron). QueueJobLogMiddleware enters and leaves it; a stack,
 * because a handler may dispatch a message of its own.
 */
class QueueJobLogContext
{
    /** @var QueueJobLog[] */
    private array $logs = [];

    /**
     * @param QueueJobLog $queueJobLog The line of the message that is about to be handled.
     *
     * @return void
     */
    public function enter(QueueJobLog $queueJobLog): void
    {
        $this->logs[] = $queueJobLog;
    }

    /**
     * @return void
     */
    public function leave(): void
    {
        array_pop($this->logs);
    }

    /**
     * @return QueueJobLog|null The line of the message being handled, or null outside the middleware.
     */
    public function current(): ?QueueJobLog
    {
        return $this->logs === [] ? null : $this->logs[array_key_last($this->logs)];
    }
}
