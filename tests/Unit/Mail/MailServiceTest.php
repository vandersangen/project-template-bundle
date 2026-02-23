<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Mail;

use VanDerSangen\ProjectTemplateBundle\Mail\Entity\Mail;
use VanDerSangen\ProjectTemplateBundle\Mail\Enum\MailStatus;
use VanDerSangen\ProjectTemplateBundle\Mail\Repository\MailRepository;
use VanDerSangen\ProjectTemplateBundle\Mail\Service\MailService;
use VanDerSangen\ProjectTemplateBundle\Mail\Template\DefaultMailTemplate;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\SendMailMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\Service\QueueService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

class MailServiceTest extends TestCase
{
    private QueueService $queueService;
    private MailRepository $mailRepository;
    private MailService $mailService;

    protected function setUp(): void
    {
        $this->queueService = $this->createMock(QueueService::class);
        $this->mailRepository = $this->createMock(MailRepository::class);
        $this->mailService = new MailService(
            $this->queueService,
            $this->mailRepository,
            'noreply@example.com'
        );
    }

    public function testCreateMailWithDefaultSender(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Mail::class), true);
        $mail = $this->mailService->createMail(
            'Test Subject',
            '<p>Test Body</p>',
            ['recipient@example.com']
        );
        $this->assertEquals('noreply@example.com', $mail->getSender());
        $this->assertEquals(['recipient@example.com'], $mail->getReceiver());
        $this->assertEquals('Test Subject', $mail->getTitle());
        $this->assertEquals('<p>Test Body</p>', $mail->getBody());
        $this->assertEquals(MailStatus::PENDING, $mail->getStatus());
        $this->assertNull($mail->getCc());
        $this->assertNull($mail->getBcc());
    }

    public function testCreateMailWithCustomSender(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save');
        $mail = $this->mailService->createMail(
            'Test Subject',
            '<p>Body</p>',
            ['recipient@example.com'],
            'custom@example.com'
        );
        $this->assertEquals('custom@example.com', $mail->getSender());
    }

    public function testCreateMailWithCcAndBcc(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save');
        $mail = $this->mailService->createMail(
            'Test Subject',
            '<p>Body</p>',
            ['recipient@example.com'],
            null,
            ['cc@example.com'],
            ['bcc@example.com']
        );
        $this->assertEquals(['cc@example.com'], $mail->getCc());
        $this->assertEquals(['bcc@example.com'], $mail->getBcc());
    }

    public function testRenderTemplate(): void
    {
        $template = '<h1>{{ title }}</h1><p>{{ message }}</p>';
        $result = $this->mailService->renderTemplate($template, [
            'title' => 'Hello',
            'message' => 'World',
        ]);
        $this->assertEquals('<h1>Hello</h1><p>World</p>', $result);
    }

    public function testRenderTemplateWithNoParameters(): void
    {
        $template = '<p>Static content</p>';
        $result = $this->mailService->renderTemplate($template);
        $this->assertEquals('<p>Static content</p>', $result);
    }

    public function testLoadTemplate(): void
    {
        $content = $this->mailService->loadTemplate(DefaultMailTemplate::BASE);
        $this->assertStringContainsString('{{ title }}', $content);
        $this->assertStringContainsString('{{ content }}', $content);
        $this->assertStringContainsString('{{ footer }}', $content);
    }

    public function testLoadTemplateThrowsExceptionForInvalidTemplate(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->mailService->loadTemplate('non_existent_template');
    }

    public function testCreateFromTemplate(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save');
        $mail = $this->mailService->createFromTemplate(
            'Test Subject',
            DefaultMailTemplate::BASE,
            ['title' => 'Hello', 'content' => 'World', 'footer' => 'Footer'],
            ['recipient@example.com']
        );
        $this->assertStringContainsString('Hello', $mail->getBody());
        $this->assertStringContainsString('World', $mail->getBody());
        $this->assertEquals('Test Subject', $mail->getTitle());
    }

    public function testSendDispatchesViaQueue(): void
    {
        $mail = $this->createMock(Mail::class);
        $mail->expects($this->once())
            ->method('getId')
            ->willReturn(42);
        $this->queueService->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn(SendMailMessage $message) => $message->getMailId() === 42))
            ->willReturn(new Envelope(new SendMailMessage(42)));
        $this->mailService->send($mail);
    }

    public function testCreateAndSendDispatchesViaQueue(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Mail $mail) {
                $reflection = new \ReflectionProperty(Mail::class, 'id');
                $reflection->setValue($mail, 1);
            });
        $this->queueService->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendMailMessage::class))
            ->willReturn(new Envelope(new SendMailMessage(1)));
        $mail = $this->mailService->createAndSend(
            'Test Subject',
            '<p>Body</p>',
            ['recipient@example.com']
        );
        $this->assertEquals(MailStatus::PENDING, $mail->getStatus());
    }

    public function testCreateFromTemplateAndSendDispatchesViaQueue(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Mail $mail) {
                $reflection = new \ReflectionProperty(Mail::class, 'id');
                $reflection->setValue($mail, 2);
            });
        $this->queueService->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendMailMessage::class))
            ->willReturn(new Envelope(new SendMailMessage(2)));
        $mail = $this->mailService->createFromTemplateAndSend(
            'Welcome',
            DefaultMailTemplate::WELCOME,
            ['name' => 'John', 'email' => 'john@example.com'],
            ['john@example.com']
        );
        $this->assertStringContainsString('John', $mail->getBody());
        $this->assertEquals(MailStatus::PENDING, $mail->getStatus());
    }

    public function testLoadAndRenderBaseTemplate(): void
    {
        $template = $this->mailService->loadTemplate(DefaultMailTemplate::BASE);
        $result = $this->mailService->renderTemplate($template, [
            'title' => 'Welcome',
            'content' => '<p>Hello World</p>',
            'footer' => 'Copyright 2026',
        ]);
        $this->assertStringContainsString('Welcome', $result);
        $this->assertStringContainsString('<p>Hello World</p>', $result);
        $this->assertStringContainsString('Copyright 2026', $result);
    }

    public function testLoadAndRenderNotificationTemplate(): void
    {
        $template = $this->mailService->loadTemplate(DefaultMailTemplate::NOTIFICATION);
        $result = $this->mailService->renderTemplate($template, [
            'title' => 'Alert',
            'message' => 'Something happened',
        ]);
        $this->assertStringContainsString('Alert', $result);
        $this->assertStringContainsString('Something happened', $result);
    }

    public function testLoadAndRenderPasswordResetTemplate(): void
    {
        $template = $this->mailService->loadTemplate(DefaultMailTemplate::PASSWORD_RESET);
        $result = $this->mailService->renderTemplate($template, [
            'name' => 'John',
            'resetUrl' => 'https://example.com/reset/abc123',
            'expiry' => '1 hour',
        ]);
        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('https://example.com/reset/abc123', $result);
        $this->assertStringContainsString('1 hour', $result);
    }

    public function testLoadAndRenderWelcomeTemplate(): void
    {
        $template = $this->mailService->loadTemplate(DefaultMailTemplate::WELCOME);
        $result = $this->mailService->renderTemplate($template, [
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);
        $this->assertStringContainsString('Jane', $result);
        $this->assertStringContainsString('jane@example.com', $result);
    }

    public function testLoadAndRenderPasswordResetConfirmationTemplate(): void
    {
        $template = $this->mailService->loadTemplate(DefaultMailTemplate::PASSWORD_RESET_CONFIRMATION);
        $result = $this->mailService->renderTemplate($template, ['name' => 'John']);
        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('Password Changed Successfully', $result);
    }

    public function testSendCreatesCorrectMessage(): void
    {
        $mail = $this->createMock(Mail::class);
        $mail->expects($this->once())
            ->method('getId')
            ->willReturn(123);
        $capturedMessage = null;
        $this->queueService->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SendMailMessage $message) use (&$capturedMessage) {
                $capturedMessage = $message;
                return true;
            }))
            ->willReturn(new Envelope(new SendMailMessage(123)));
        $this->mailService->send($mail);
        $this->assertInstanceOf(SendMailMessage::class, $capturedMessage);
        $this->assertEquals(123, $capturedMessage->getMailId());
    }

    public function testCreateMailSetsStatusToPending(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save');
        $mail = $this->mailService->createMail(
            'Subject',
            '<p>Body</p>',
            ['test@example.com']
        );
        $this->assertEquals(MailStatus::PENDING, $mail->getStatus());
    }

    public function testCreateAndSendWithCcAndBcc(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Mail $mail) {
                $reflection = new \ReflectionProperty(Mail::class, 'id');
                $reflection->setValue($mail, 3);
            });
        $this->queueService->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendMailMessage::class))
            ->willReturn(new Envelope(new SendMailMessage(3)));
        $mail = $this->mailService->createAndSend(
            'Test Subject',
            '<p>Body</p>',
            ['recipient@example.com'],
            null,
            ['cc@example.com'],
            ['bcc@example.com']
        );
        $this->assertEquals(['cc@example.com'], $mail->getCc());
        $this->assertEquals(['bcc@example.com'], $mail->getBcc());
    }

    public function testCreateAndSendWithMultipleReceivers(): void
    {
        $this->mailRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Mail $mail) {
                $reflection = new \ReflectionProperty(Mail::class, 'id');
                $reflection->setValue($mail, 4);
            });
        $this->queueService->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendMailMessage::class))
            ->willReturn(new Envelope(new SendMailMessage(4)));
        $mail = $this->mailService->createAndSend(
            'Test Subject',
            '<p>Body</p>',
            ['a@example.com', 'b@example.com', 'c@example.com']
        );
        $this->assertEquals(['a@example.com', 'b@example.com', 'c@example.com'], $mail->getReceiver());
    }
}
