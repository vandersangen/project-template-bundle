<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Message;

/**
 * Runs a cron in the worker. A run started by hand can carry a run id of its own choosing: it lands in the message data
 * of the queue_job_logs line, so the run can be found there and followed while it runs.
 */
class RunCronMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly int $cronId,
        private readonly ?string $runId = null,
    ) {
    }

    public function getCronId(): int
    {
        return $this->cronId;
    }

    public function getRunId(): ?string
    {
        return $this->runId;
    }
}
