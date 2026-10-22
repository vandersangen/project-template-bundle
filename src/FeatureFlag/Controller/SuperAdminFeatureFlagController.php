<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Controller;

use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Exception\UnknownFeatureFlagException;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagAudienceRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagAudienceRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * The feature flags of this environment, each with a switch per audience: only a super admin sees and switches them.
 *
 * The bundle's own SuperAdmin module is a session firewall on ^/super-admin and does not cover /api/super-admin. The
 * project needs its own stateless firewall on ^/api/super-admin, before the ^/api one, whose user has ROLE_SUPER_ADMIN;
 * otherwise a JWT user with ROLE_SUPER_ADMIN on the ^/api firewall passes as well (docs/feature-flags.md).
 */
#[Route('/api/super-admin/feature-flags', name: 'super_admin_feature_flags_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class SuperAdminFeatureFlagController extends AbstractController
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly FeatureFlagRepository $featureFlagRepository,
        private readonly FeatureFlagAudienceRepository $featureFlagAudienceRepository,
        private readonly FeatureFlagAudienceRegistry $featureFlagAudienceRegistry,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listFeatureFlags(): JsonResponse
    {
        return $this->json(['featureFlags' => $this->describeFeatureFlags()]);
    }

    /**
     * @param string  $cid      The cid of the flag to switch.
     * @param string  $audience The key of the audience whose switch changes.
     * @param Request $request  A JSON body {"on": bool}.
     *
     * @return JsonResponse The list as GET answers it, 404 for an unknown flag or audience, or 422 with the error
     *                      per field.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     * @throws Throwable                When storing fails.
     */
    #[Route('/{cid}/audiences/{audience}', name: 'switch', methods: ['PUT'])]
    public function switchFeatureFlag(string $cid, string $audience, Request $request): JsonResponse
    {
        if ($this->featureFlagRepository->findFlagByCid($cid) === null
            || !$this->featureFlagAudienceRegistry->hasAudience($audience)) {
            return $this->respondUnknownFlagOrAudience();
        }

        $on = $this->readSwitchFromBody($request);

        if ($on === null) {
            return $this->json(['errors' => ['on' => 'Kies aan of uit.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->featureFlagService->switchFlag($cid, $audience, $on, $this->superAdminIdentifier());
        } catch (UnknownFeatureFlagException) {
            return $this->respondUnknownFlagOrAudience();
        }

        return $this->json(['featureFlags' => $this->describeFeatureFlags()]);
    }

    /**
     * Every flag with its state per configured audience, from one query each for the flags and the switches.
     *
     * @return list<array<string, mixed>>
     */
    private function describeFeatureFlags(): array
    {
        $switches = [];

        foreach ($this->featureFlagAudienceRepository->findAllSwitches() as $audienceSwitch) {
            $switches[$audienceSwitch->getFeatureFlag()->getCid()][$audienceSwitch->getAudience()] = $audienceSwitch;
        }

        $described = [];

        foreach ($this->featureFlagRepository->findAllFlags() as $flag) {
            $audiences = [];

            foreach ($this->featureFlagAudienceRegistry->audiences() as $key => $audience) {
                $audiences[] = $this->describeAudience($key, $audience, $switches[$flag->getCid()][$key] ?? null);
            }

            $described[] = [
                'cid' => $flag->getCid(),
                'name' => $flag->getName(),
                'description' => $flag->getDescription(),
                'audiences' => $audiences,
            ];
        }

        return $described;
    }

    /**
     * @param string                            $key            The key of the audience.
     * @param array{label: string, guest: bool} $audience       Its configuration.
     * @param FeatureFlagAudience|null          $audienceSwitch Its stored switch for the flag, or null when never
     *                                                          switched.
     *
     * @return array<string, mixed>
     */
    private function describeAudience(string $key, array $audience, ?FeatureFlagAudience $audienceSwitch): array
    {
        return [
            'key' => $key,
            'label' => $audience['label'],
            'guest' => $audience['guest'],
            'on' => $audienceSwitch?->isOn() ?? false,
            'updatedAt' => $audienceSwitch?->getUpdatedAt()->format(\DATE_ATOM),
            'updatedBy' => $audienceSwitch?->getUpdatedBy(),
        ];
    }

    /**
     * @param Request $request The request with a JSON body {"on": bool}.
     *
     * @return bool|null The switch, or null when the body is no JSON or "on" is not a bool.
     */
    private function readSwitchFromBody(Request $request): ?bool
    {
        if ($request->getContent() === '') {
            return null;
        }

        try {
            $on = $request->toArray()['on'] ?? null;
        } catch (JsonException) {
            return null;
        }

        return \is_bool($on) ? $on : null;
    }

    private function respondUnknownFlagOrAudience(): JsonResponse
    {
        return $this->json(['error' => 'Deze feature flag of doelgroep bestaat niet.'], Response::HTTP_NOT_FOUND);
    }

    private function superAdminIdentifier(): string
    {
        return (string) $this->getUser()?->getUserIdentifier();
    }
}
