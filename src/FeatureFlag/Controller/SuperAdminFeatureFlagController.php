<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Controller;

use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagSetting;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagSettingRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * The feature flags of this environment, each with a switch per audience: only a super admin sees and switches them.
 *
 * The project protects /api/super-admin with its own super-admin authentication (docs/feature-flags.md).
 */
#[Route('/api/super-admin/feature-flags', name: 'super_admin_feature_flags_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class SuperAdminFeatureFlagController extends AbstractController
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly FeatureFlagRegistry $featureFlagRegistry,
        private readonly FeatureFlagSettingRepository $featureFlagSettingRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listFeatureFlags(): JsonResponse
    {
        return $this->json(['featureFlags' => $this->describeFeatureFlags()]);
    }

    /**
     * @param string  $key     The key of the flag to switch.
     * @param Request $request A JSON body {"audience": "<key>", "on": bool}.
     *
     * @return JsonResponse The list, as GET answers it, or 422 with the error per field.
     *
     * @throws NotFoundHttpException    When the project has no flag with that key.
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    #[Route('/{key}', name: 'switch', methods: ['PUT'])]
    public function switchFeatureFlag(string $key, Request $request): JsonResponse
    {
        $featureFlag = $this->featureFlagRegistry->findFlag($key)
            ?? throw $this->createNotFoundException('Deze feature flag bestaat niet.');
        $body = $request->toArray();
        $audienceKey = $body['audience'] ?? null;
        $audience = \is_string($audienceKey) ? $this->featureFlagRegistry->findAudience($audienceKey) : null;
        $on = $body['on'] ?? null;

        if ($audience === null) {
            return $this->json(['errors' => ['audience' => 'Kies voor wie.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!\is_bool($on)) {
            return $this->json(['errors' => ['on' => 'Kies aan of uit.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->featureFlagService->switchFlag($featureFlag, $audience, $on, $this->superAdminIdentifier());

        return $this->json(['featureFlags' => $this->describeFeatureFlags()]);
    }

    /**
     * Every configured flag with its state per configured audience, from one query. A flag never switched for an
     * audience is off for it; a row of a flag or audience the project no longer has is left out.
     *
     * @return list<array{key: string, description: string, audiences: list<array<string, mixed>>}>
     */
    private function describeFeatureFlags(): array
    {
        $settings = [];

        foreach ($this->featureFlagSettingRepository->findAllSettings() as $setting) {
            $settings[$setting->getFeatureFlagKey()][$setting->getAudienceKey()] = $setting;
        }

        $described = [];

        foreach ($this->featureFlagRegistry->flags() as $featureFlag) {
            $audiences = [];

            foreach ($this->featureFlagRegistry->audiences() as $audience) {
                $setting = $settings[$featureFlag->key()][$audience->key()] ?? null;
                $audiences[] = $this->describeAudience($audience, $setting);
            }

            $described[] = [
                'key' => $featureFlag->key(),
                'description' => $featureFlag->describe(),
                'audiences' => $audiences,
            ];
        }

        return $described;
    }

    /**
     * @param FeatureFlagAudienceInterface $audience The audience.
     * @param FeatureFlagSetting|null      $setting  Its stored setting for the flag, or null when never switched.
     *
     * @return array{key: string, description: string, on: bool, updatedAt: ?string, updatedBy: ?string}
     */
    private function describeAudience(FeatureFlagAudienceInterface $audience, ?FeatureFlagSetting $setting): array
    {
        return [
            'key' => $audience->key(),
            'description' => $audience->describe(),
            'on' => $setting?->isOn() ?? false,
            'updatedAt' => $setting?->getUpdatedAt()->format(\DATE_ATOM),
            'updatedBy' => $setting?->getUpdatedBy(),
        ];
    }

    private function superAdminIdentifier(): string
    {
        return (string) $this->getUser()?->getUserIdentifier();
    }
}
