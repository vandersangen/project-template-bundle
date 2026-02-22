<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Message;

class SendMailMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly int $mailId,
    ) {
    }

    public function getMailId(): int
    {
        return $this->mailId;
    }
}
