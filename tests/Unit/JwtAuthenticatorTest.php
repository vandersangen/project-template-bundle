<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit;

use VanDerSangen\ProjectTemplateBundle\Auth\Security\JwtAuthenticator;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use VanDerSangen\ProjectTemplateBundle\User\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class JwtAuthenticatorTest extends TestCase
{
    private $jwtManager;
    private $userRepository;
    private $authenticator;

    protected function setUp(): void
    {
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->authenticator = new JwtAuthenticator($this->jwtManager, $this->userRepository);
    }

    public function testSupportsReturnsFalseForLoginEndpoint(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/auth/login']);

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseForRegisterEndpoint(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/auth/register']);

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseWhenNoAuthorizationHeader(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/hello']);

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsTrueWhenAuthorizationHeaderPresent(): void
    {
        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/api/hello',
            'HTTP_AUTHORIZATION' => 'Bearer some.jwt.token',
        ]);

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testAuthenticateThrowsExceptionWhenNoTokenProvided(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/api/hello']);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No token provided');

        $this->authenticator->authenticate($request);
    }

    public function testStartReturnsJsonResponseWithError(): void
    {
        $request = new Request();

        $response = $this->authenticator->start($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Authentication required', $data['error']);
    }

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $firewallName = 'api';

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, $firewallName);

        $this->assertNull($result);
    }

    public function testOnAuthenticationFailureReturnsJsonResponse(): void
    {
        $request = new Request();
        $exception = new AuthenticationException('Invalid token');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid token', $data['error']);
    }
}
