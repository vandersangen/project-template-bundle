<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Auth\Controller;

use VanDerSangen\ProjectTemplateBundle\Auth\Service\TotpService;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use VanDerSangen\ProjectTemplateBundle\User\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Opt-in TOTP two-factor enrollment for the authenticated user.
 *
 * Enrollment is a two-step handshake: setup() hands out a secret (never
 * enabled yet), and enable() confirms the user can produce a valid code before
 * 2FA is switched on and one-time backup codes are returned. All routes live
 * under the JWT-protected /api firewall.
 */
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TotpService $totpService,
        private readonly UserService $userService,
        private readonly string $issuer,
    ) {
    }

    #[Route('/api/profile/2fa/status', name: 'profile_2fa_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['enabled' => $user->isTotpEnabled()]);
    }

    #[Route('/api/profile/2fa/setup', name: 'profile_2fa_setup', methods: ['POST'])]
    public function setup(): JsonResponse
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTotpEnabled()) {
            return $this->json(
                ['error' => 'Two-factor authentication is already enabled'],
                Response::HTTP_CONFLICT,
            );
        }

        $secret = $this->totpService->generateSecret();
        $user->setTotpPendingSecret($this->totpService->encryptSecret($secret));
        $this->userService->save($user);

        return $this->json([
            'secret' => $secret,
            'otpauthUri' => $this->totpService->buildProvisioningUri(
                $secret,
                (string) $user->getEmail(),
                $this->issuer,
            ),
        ]);
    }

    #[Route('/api/profile/2fa/enable', name: 'profile_2fa_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTotpEnabled()) {
            return $this->json(
                ['error' => 'Two-factor authentication is already enabled'],
                Response::HTTP_CONFLICT,
            );
        }

        $pending = $user->getTotpPendingSecret();
        if ($pending === null) {
            return $this->json(['error' => 'Start setup before enabling'], Response::HTTP_BAD_REQUEST);
        }

        $code = (string) ($this->decode($request)['code'] ?? '');
        if ($code === '') {
            return $this->json(['error' => 'Code is required'], Response::HTTP_BAD_REQUEST);
        }

        $secret = $this->totpService->decryptSecret($pending);
        if (!$this->totpService->verifyCode($secret, $code)) {
            return $this->json(['error' => 'Invalid code'], Response::HTTP_BAD_REQUEST);
        }

        $backupCodes = $this->totpService->generateBackupCodes();

        $user->setTotpSecret($pending);
        $user->setTotpPendingSecret(null);
        $user->setTotpBackupCodes($backupCodes['hashed']);
        $user->setTotpEnabled(true);
        $this->userService->save($user);

        // Backup codes are returned exactly once — the plaintext is never stored.
        return $this->json(['backupCodes' => $backupCodes['plain']]);
    }

    #[Route('/api/profile/2fa/disable', name: 'profile_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isTotpEnabled() || $user->getTotpSecret() === null) {
            return $this->json(['enabled' => false]);
        }

        $code = (string) ($this->decode($request)['code'] ?? '');
        if ($code === '') {
            return $this->json(['error' => 'Code is required'], Response::HTTP_BAD_REQUEST);
        }

        // Require a valid current code (or a backup code) to switch 2FA off, so a
        // stolen but still-authenticated session cannot silently disable it.
        $secret = $this->totpService->decryptSecret($user->getTotpSecret());
        $validCode = $this->totpService->verifyCode($secret, $code)
            || $this->totpService->consumeBackupCode($user->getTotpBackupCodes() ?? [], $code) !== null;

        if (!$validCode) {
            return $this->json(['error' => 'Invalid code'], Response::HTTP_BAD_REQUEST);
        }

        $user->setTotpEnabled(false);
        $user->setTotpSecret(null);
        $user->setTotpPendingSecret(null);
        $user->setTotpBackupCodes(null);
        $this->userService->save($user);

        return $this->json(['enabled' => false]);
    }

    private function requireUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }
}
