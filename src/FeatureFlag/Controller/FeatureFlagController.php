<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Controller;

use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * Which feature flags are on for the caller, for the frontend to hide what is off.
 *
 * Only the cids of the flags on for the caller: a flag that is off is not mentioned at all. The route has no
 * #[IsGranted]: the project opens this path for visitors (`access_control` with PUBLIC_ACCESS), because guests can
 * have flags too.
 */
final class FeatureFlagController extends AbstractController
{
    public function __construct(private readonly FeatureFlagService $featureFlagService)
    {
    }

    /**
     * @return JsonResponse
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    #[Route('/api/feature-flags', name: 'feature_flags_list', methods: ['GET'])]
    public function listFlagsOnForCaller(): JsonResponse
    {
        return $this->json(['featureFlags' => $this->featureFlagService->listCidsOn()]);
    }
}
