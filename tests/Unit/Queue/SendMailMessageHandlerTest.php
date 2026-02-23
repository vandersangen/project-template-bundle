<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\MailStatus;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\MailRepository;
use VanDerSangen\ProjectTemplateBundle\Queue\Handler\AsyncMessageHandlerInterface;
use VanDerSangen\ProjectTemplateBundle\Queue\Handler\SendMailMessageHandler;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\SendMailMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SendMailMessageHandlerTest extends TestCase
{
    private MailerInterface $mailer;
    private MailRepository $mailRepository;
    private SendMailMessageHandler $handler;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->mailRepository = $this->createMock(MailRepository::class);
        $this->handler = new SendMailMessageHandler($this->mailer, $this->mailRepository);
    }

    public function testImplementsAsyncMessageHandlerInterface(): void
    {
        $this->assertInstanceOf(AsyncMessageHandlerInterface::class, $this->handler);
    }

    public function testInvokeSuccessfullySendsMail(): void
    {
        $mail = new Mail();
        $mail->setSender('sender@example.com');
        $mail->setReceiver(['recipient@example.com']);
        $mail->setTitle('Test Subject');
        $mail->setBody('<p>Test Body</p>');
        $mail->setStatus(MailStatus::PENDING);
        $this->mailRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($mail);
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->with($mail, true);
        $message = new SendMailMessage(1);
        ($this->handler)($message);
        $this->assertEquals(MailStatus::SENT, $mail->getStatus());
        $this->assertNotNull($mail->getSentAt());
    }

    public function testInvokeThrowsExceptionWhenMailNotFound(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mail with ID 999 not found.');
        $message = new SendMailMessage(999);
        ($this->handler)($message);
    }

    public function testInvokeSkipsAlreadySentMail(): void
    {
        $mail = new Mail();
        $mail->setSender('sender@example.com');
        $mail->setReceiver(['recipient@example.com']);
        $mail->setTitle('Test');
        $mail->setBody('<p>Body</p>');
        $mail->setStatus(MailStatus::SENT);
        $this->mailRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($mail);
        $this->mailer->expects($this->never())
            ->method('send');
        $this->mailRepository->expects($this->never())
            ->method('save');
        $message = new SendMailMessage(1);
        ($this->handler)($message);
    }

    public function testInvokeWithCcAndBcc(): void
    {
        $mail = new Mail();
        $mail->setSender('sender@example.com');
        $mail->setReceiver(['recipient@example.com']);
        $mail->setCc(['cc@example.com']);
        $mail->setBcc(['bcc@example.com']);
        $mail->setTitle('Test Subject');
        $mail->setBody('<p>Test Body</p>');
        $mail->setStatus(MailStatus::PENDING);
        $this->mailRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($mail);
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $toAddresses = array_map(fn ($a) => $a->getAddress(), $email->getTo());
                $ccAddresses = array_map(fn ($a) => $a->getAddress(), $email->getCc());
                $bccAddresses = array_map(fn ($a) => $a->getAddress(), $email->getBcc());
                return in_array('recipient@example.com', $toAddresses)
                    && in_array('cc@example.com', $ccAddresses)
                    && in_array('bcc@example.com', $bccAddresses);
            }));
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->with($mail, true);
        $message = new SendMailMessage(1);
        ($this->handler)($message);
        $this->assertEquals(MailStatus::SENT, $mail->getStatus());
    }

    public function testInvokeMailerExceptionBubblesUp(): void
    {
        $mail = new Mail();
        $mail->setSender('sender@example.com');
        $mail->setReceiver(['recipient@example.com']);
        $mail->setTitle('Test');
        $mail->setBody('<p>Body</p>');
        $mail->setStatus(MailStatus::PENDING);
        $this->mailRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($mail);
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Transport error'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transport error');
        $message = new SendMailMessage(1);
        ($this->handler)($message);
    }

    public function testInvokeWithMultipleReceivers(): void
    {
        $mail = new Mail();
        $mail->setSender('sender@example.com');
        $mail->setReceiver(['a@example.com', 'b@example.com', 'c@example.com']);
        $mail->setTitle('Test');
        $mail->setBody('<p>Body</p>');
        $mail->setStatus(MailStatus::PENDING);
        $this->mailRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($mail);
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $toAddresses = array_map(fn ($a) => $a->getAddress(), $email->getTo());
                return count($toAddresses) === 3
                    && in_array('a@example.com', $toAddresses)
                    && in_array('b@example.com', $toAddresses)
                    && in_array('c@example.com', $toAddresses);
            }));
        $this->mailRepository->expects($this->once())
            ->method('save');
        $message = new SendMailMessage(2);
        ($this->handler)($message);
        $this->assertEquals(MailStatus::SENT, $mail->getStatus());
    }

    public function testInvokeRetriesFailedMail(): void
    {
        $mail = new Mail();
        $mail->setSender('sender@example.com');
        $mail->setReceiver(['recipient@example.com']);
        $mail->setTitle('Test');
        $mail->setBody('<p>Body</p>');
        $mail->setStatus(MailStatus::FAILED);
        $this->mailRepository->expects($this->once())
            ->method('find')
            ->with(3)
            ->willReturn($mail);
        $this->mailer->expects($this->once())
            ->method('send');
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->with($mail, true);
        $message = new SendMailMessage(3);
        ($this->handler)($message);
        $this->assertEquals(MailStatus::SENT, $mail->getStatus());
        $this->assertNotNull($mail->getSentAt());
    }
}
