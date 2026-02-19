<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Auth\Security;

use LarsVanDerSangen\ProjectTemplateBundle\User\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const int INACTIVITY_TIMEOUT = 1800; // 30 minutes in seconds

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepository $userRepository
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Skip authentication for login and register endpoints
        if (str_contains($request->getPathInfo(), '/api/auth/login') ||
            str_contains($request->getPathInfo(), '/api/auth/register')) {
            return false;
        }

        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
        return $extractor->extract($request) !== false;
    }

    public function authenticate(Request $request): Passport
    {
        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'Authorization');
        $token = $extractor->extract($request);

        if (!$token) {
            throw new AuthenticationException('No token provided');
        }

        try {
            $payload = $this->jwtManager->parse($token);

            // Check inactivity timeout
            if (isset($payload['iat']) && (time() - $payload['iat']) > self::INACTIVITY_TIMEOUT) {
                throw new AuthenticationException('Token expired due to inactivity');
            }

            // Store the original token and payload in request attributes for later use
            $request->attributes->set('_jwt_token', $token);
            $request->attributes->set('_jwt_payload', $payload);

            return new SelfValidatingPassport(
                new UserBadge(
                    $payload['username'],
                    fn($userIdentifier) => $this->userRepository->findByEmail($userIdentifier)
                )
            );
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @param string $firewallName
     *
     * @return Response|null
     *
     * @SuppressWarnings(PHPMD)
     */
    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        // Allow the request to continue
        return null;
    }

    /**
     * @param Request $request
     * @param AuthenticationException $exception
     *
     * @return Response|null
     *
     * @SuppressWarnings(PHPMD)
     */
    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): ?Response {
        return new JsonResponse([
            'error' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     * This should return a response that "helps" the user start the authentication process.
     *
     * @SuppressWarnings(PHPMD)
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $data = [
            'error' => 'Authentication required',
            'message' => 'Full authentication is required to access this resource.',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}

