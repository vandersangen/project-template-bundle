<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\Mail;

use VanDerSangen\ProjectTemplateBundle\User\Entity\User;

/**
 * Sends the account/system e-mails triggered by {@see \VanDerSangen\ProjectTemplateBundle\Auth\Service\AuthService}.
 *
 * The bundle ships {@see DefaultAuthMailSender} (renders + sends locally, the
 * historical behaviour). A consuming project can bind its own implementation —
 * e.g. one that delegates to a central, branded mail service — by aliasing this
 * interface to that implementation in its own services configuration.
 */
interface AuthMailSenderInterface
{
    public function sendWelcome(User $user): void;

    /**
     * @param User   $user       The user requesting the reset.
     * @param string $resetToken The raw reset token; the implementation decides
     *                           how to turn it into a user-facing reset link.
     */
    public function sendPasswordReset(User $user, string $resetToken): void;

    public function sendPasswordResetConfirmation(User $user): void;
}
