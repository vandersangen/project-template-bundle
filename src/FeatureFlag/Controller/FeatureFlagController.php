<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Controller;

use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * Which feature flags are on for the signed-in caller, for the frontend to hide what is off.
 *
 * Only the keys of the flags on for the caller's own audience: a flag that is off is not mentioned at all. A project
 * without flags answers an empty list, without asking the resolver.
 */
#[IsGranted('IS_AUTHENTICATED')]
final class FeatureFlagController extends AbstractController
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly FeatureFlagRegistry $featureFlagRegistry,
        private readonly FeatureFlagAudienceResolverInterface $featureFlagAudienceResolver,
    ) {
    }

    /**
     * @return JsonResponse
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    #[Route('/api/feature-flags', name: 'feature_flags_list', methods: ['GET'])]
    public function listFlagsOnForCaller(): JsonResponse
    {
        if ($this->featureFlagRegistry->flags() === []) {
            return $this->json(['featureFlags' => []]);
        }

        $audience = $this->featureFlagAudienceResolver->resolveAudienceOfCurrentRequest();
        $flagsOn = $this->featureFlagService->listFlagsOnFor($audience);

        return $this->json([
            'featureFlags' => array_map(
                static fn (FeatureFlagInterface $featureFlag): string => $featureFlag->key(),
                $flagsOn,
            ),
        ]);
    }
}
