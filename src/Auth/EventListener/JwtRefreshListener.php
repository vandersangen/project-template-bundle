<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: 0)]
class JwtRefreshListener
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only process if user is authenticated and we have a JWT token
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        // Skip for login/register endpoints (they already return a token in the body)
        if (str_contains($request->getPathInfo(), '/api/auth/login') ||
            str_contains($request->getPathInfo(), '/api/auth/register')) {
            return;
        }

        // Check if this request was authenticated with JWT
        $originalPayload = $request->attributes->get('_jwt_payload');
        if (!$originalPayload) {
            return;
        }

        // Generate a new token with refreshed timestamp
        $user = $token->getUser();
        $newToken = $this->jwtManager->create($user);

        // Add the refreshed token to response headers
        $response->headers->set('X-Auth-Token', $newToken);
    }
}
