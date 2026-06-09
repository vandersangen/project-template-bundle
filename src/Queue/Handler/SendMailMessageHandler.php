<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Handler;

use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\MailStatus;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\MailRepository;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\SendMailMessage;
use DateTimeImmutable;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class SendMailMessageHandler implements AsyncMessageHandlerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MailRepository $mailRepository,
    ) {
    }

    public function __invoke(SendMailMessage $message): void
    {
        $mail = $this->mailRepository->find($message->getMailId());
        if (!$mail instanceof Mail) {
            throw new \RuntimeException(sprintf('Mail with ID %d not found.', $message->getMailId()));
        }

        if ($mail->getStatus() === MailStatus::SENT) {
            return;
        }

        $email = new Email()
            ->from($mail->getSender())
            ->subject($mail->getTitle())
            ->html($mail->getBody());

        foreach ($mail->getReceiver() as $recipient) {
            $email->addTo($recipient);
        }

        if ($mail->getCc()) {
            foreach ($mail->getCc() as $ccAddress) {
                $email->addCc($ccAddress);
            }
        }

        if ($mail->getBcc()) {
            foreach ($mail->getBcc() as $bccAddress) {
                $email->addBcc($bccAddress);
            }
        }

        foreach ($mail->getAttachments() ?? [] as $attachment) {
            // Inline (base64-encoded) content takes precedence over a path so
            // attachments can live in the database without touching the disk.
            $content = $attachment['content'] ?? null;
            if (is_string($content) && $content !== '') {
                $decoded = base64_decode($content, true);
                if ($decoded !== false) {
                    $email->attach(
                        $decoded,
                        $attachment['filename'] ?? null,
                        $attachment['mime'] ?? null,
                    );
                }
                continue;
            }

            $path = $attachment['path'] ?? null;
            if (!is_string($path) || !is_file($path)) {
                continue;
            }
            $email->attachFromPath(
                $path,
                $attachment['filename'] ?? null,
                $attachment['mime'] ?? null,
            );
        }

        $this->mailer->send($email);

        $mail->setStatus(MailStatus::SENT);
        $mail->setSentAt(new DateTimeImmutable());

        $this->mailRepository->save($mail, true);
    }
}
