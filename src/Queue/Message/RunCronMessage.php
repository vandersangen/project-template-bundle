<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Message;

class RunCronMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly int $cronId,
    ) {
    }

    public function getCronId(): int
    {
        return $this->cronId;
    }
}
