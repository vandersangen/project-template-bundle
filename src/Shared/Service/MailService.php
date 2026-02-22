<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $senderEmail
    ) {
    }

    public function send(string $recipient, string $subject, string $content, bool $isHtml = true): void
    {
        $email = new Email()
            ->from($this->senderEmail)
            ->to($recipient)
            ->subject($subject);

        if ($isHtml) {
            $email->html($content);
        }
        if (!$isHtml) {
            $email->text($content);
        }

        $this->mailer->send($email);
    }
}
