<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Auth\EventListener;

use LarsVanDerSangen\ProjectTemplateBundle\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
class JwtCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();

        // Add user data to the payload
        // Note: With declare(strict_types=1), the lcobucci/jwt library does not accept null values
        // in the payload. We must only add non-null values.
        $userId = $user->getId();
        if ($userId !== null) {
            $payload['id'] = $userId;
        }

        $email = $user->getEmail();
        if ($email !== null) {
            $payload['email'] = $email;
        }

        $firstName = $user->getFirstName();
        if ($firstName !== null) {
            $payload['firstName'] = $firstName;
        }

        $lastName = $user->getLastName();
        if ($lastName !== null) {
            $payload['lastName'] = $lastName;
        }

        $createdAt = $user->getCreatedAt();
        if ($createdAt !== null) {
            $payload['createdAt'] = $createdAt->format('c');
        }

        $event->setData($payload);
    }
}

