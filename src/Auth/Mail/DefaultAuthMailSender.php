<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\Mail;

use VanDerSangen\ProjectTemplateBundle\Mail\Service\MailService;
use VanDerSangen\ProjectTemplateBundle\Mail\Template\DefaultMailTemplate;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;

/**
 * Default auth-mail sender: renders the bundle's built-in templates and sends
 * them via the local {@see MailService}. This is the historical behaviour and
 * the fallback for projects that do not centralise their system e-mails.
 */
class DefaultAuthMailSender implements AuthMailSenderInterface
{
    public function __construct(
        private readonly MailService $mailService,
    ) {
    }

    public function sendWelcome(User $user): void
    {
        $this->mailService->createFromTemplateAndSend(
            'Welcome to our platform',
            DefaultMailTemplate::WELCOME,
            [
                'name' => $user->getFirstName(),
                'email' => (string) $user->getEmail(),
            ],
            [(string) $user->getEmail()],
        );
    }

    public function sendPasswordReset(User $user, string $resetToken): void
    {
        $this->mailService->createFromTemplateAndSend(
            'Password Reset Request',
            DefaultMailTemplate::PASSWORD_RESET,
            [
                'name' => $user->getFirstName(),
                'resetUrl' => $resetToken,
                'expiry' => '1 hour',
            ],
            [(string) $user->getEmail()],
        );
    }

    public function sendPasswordResetConfirmation(User $user): void
    {
        $this->mailService->createFromTemplateAndSend(
            'Password Changed Successfully',
            DefaultMailTemplate::PASSWORD_RESET_CONFIRMATION,
            [
                'name' => $user->getFirstName(),
            ],
            [(string) $user->getEmail()],
        );
    }
}
