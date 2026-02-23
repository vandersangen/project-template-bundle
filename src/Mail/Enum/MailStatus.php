<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Enum;

enum MailStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
