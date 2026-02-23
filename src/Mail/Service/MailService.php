<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Mail\Service;

use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\MailStatus;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\MailRepository;
use VanDerSangen\ProjectTemplateBundle\Mail\Template\DefaultMailTemplate;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\SendMailMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\Service\QueueService;

class MailService
{
    public function __construct(
        private readonly QueueService $queueService,
        private readonly MailRepository $mailRepository,
        private readonly string $senderEmail,
    ) {
    }

    public function createMail(
        string $title,
        string $body,
        array $receiver,
        ?string $sender = null,
        ?array $cc = null,
        ?array $bcc = null,
    ): Mail {
        $mail = new Mail();
        $mail->setSender($sender ?? $this->senderEmail);
        $mail->setReceiver($receiver);
        $mail->setCc($cc);
        $mail->setBcc($bcc);
        $mail->setTitle($title);
        $mail->setBody($body);
        $mail->setStatus(MailStatus::PENDING);
        $this->mailRepository->save($mail, true);
        return $mail;
    }

    public function loadTemplate(string $templateName): string
    {
        return DefaultMailTemplate::load($templateName);
    }

    public function renderTemplate(string $template, array $parameters = []): string
    {
        $content = $template;
        foreach ($parameters as $key => $value) {
            $content = str_replace('{{ ' . $key . ' }}', (string) $value, $content);
        }
        return $content;
    }

    public function createFromTemplate(
        string $title,
        string $templateName,
        array $templateParameters,
        array $receiver,
        ?string $sender = null,
        ?array $cc = null,
        ?array $bcc = null,
    ): Mail {
        $template = $this->loadTemplate($templateName);
        $body = $this->renderTemplate($template, $templateParameters);
        return $this->createMail($title, $body, $receiver, $sender, $cc, $bcc);
    }

    public function send(Mail $mail): void
    {
        $this->queueService->dispatch(new SendMailMessage($mail->getId()));
    }

    public function createAndSend(
        string $title,
        string $body,
        array $receiver,
        ?string $sender = null,
        ?array $cc = null,
        ?array $bcc = null,
    ): Mail {
        $mail = $this->createMail($title, $body, $receiver, $sender, $cc, $bcc);
        $this->send($mail);
        return $mail;
    }

    public function createFromTemplateAndSend(
        string $title,
        string $templateName,
        array $templateParameters,
        array $receiver,
        ?string $sender = null,
        ?array $cc = null,
        ?array $bcc = null,
    ): Mail {
        $mail = $this->createFromTemplate($title, $templateName, $templateParameters, $receiver, $sender, $cc, $bcc);
        $this->send($mail);
        return $mail;
    }
}
