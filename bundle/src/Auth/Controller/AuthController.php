<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Auth\Controller;

use LarsVanDerSangen\ProjectTemplateBundle\Auth\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    #[Route('/api/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        $result = $this->authService->login($email, $password);

        if (!$result) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->json($result);
    }

    #[Route('/api/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $firstName = $data['firstName'] ?? '';
        $lastName = $data['lastName'] ?? '';

        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            return $this->json(['error' => 'All fields are required'], 400);
        }

        $result = $this->authService->register($email, $password, $firstName, $lastName);

        if (!$result) {
            return $this->json(['error' => 'Email already exists'], 409);
        }

        return $this->json($result, 201);
    }

    #[Route('/api/auth/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return $this->json(['error' => 'Email is required'], 400);
        }

        $this->authService->forgotPassword($email);

        return $this->json(['message' => 'If the email exists, a reset link has been sent']);
    }

    #[Route('/api/auth/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($token) || empty($password)) {
            return $this->json(['error' => 'Token and password are required'], 400);
        }

        $success = $this->authService->resetPassword($token, $password);

        if (!$success) {
            return $this->json(['error' => 'Invalid or expired token'], 400);
        }

        return $this->json(['message' => 'Password has been reset successfully']);
    }
}

