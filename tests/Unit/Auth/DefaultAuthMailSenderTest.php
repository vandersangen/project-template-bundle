<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\Auth\Mail\DefaultAuthMailSender;
use VanDerSangen\ProjectTemplateBundle\Mail\Service\MailService;
use VanDerSangen\ProjectTemplateBundle\Mail\Template\DefaultMailTemplate;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;

class DefaultAuthMailSenderTest extends TestCase
{
    private function user(): User
    {
        $user = new User();
        $user->setEmail('jane@example.com');
        $user->setFirstName('Jane');
        $user->setLastName('Doe');

        return $user;
    }

    public function testWelcomeUsesWelcomeTemplateAndRecipient(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->expects($this->once())
            ->method('createFromTemplateAndSend')
            ->with(
                $this->anything(),
                DefaultMailTemplate::WELCOME,
                $this->callback(
                    static fn (array $p): bool => $p['name'] === 'Jane' && $p['email'] === 'jane@example.com',
                ),
                ['jane@example.com'],
            );

        new DefaultAuthMailSender($mailService)->sendWelcome($this->user());
    }

    public function testPasswordResetPassesTokenAndExpiry(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->expects($this->once())
            ->method('createFromTemplateAndSend')
            ->with(
                $this->anything(),
                DefaultMailTemplate::PASSWORD_RESET,
                $this->callback(
                    static fn (array $p): bool => $p['resetUrl'] === 'tok-123'
                        && $p['name'] === 'Jane'
                        && isset($p['expiry']),
                ),
                ['jane@example.com'],
            );

        new DefaultAuthMailSender($mailService)->sendPasswordReset($this->user(), 'tok-123');
    }

    public function testPasswordResetConfirmationUsesConfirmationTemplate(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->expects($this->once())
            ->method('createFromTemplateAndSend')
            ->with($this->anything(), DefaultMailTemplate::PASSWORD_RESET_CONFIRMATION);

        new DefaultAuthMailSender($mailService)->sendPasswordResetConfirmation($this->user());
    }
}
