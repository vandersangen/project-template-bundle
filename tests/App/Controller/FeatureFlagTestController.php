<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;

/**
 * One action behind a feature flag, on a controller whose class carries none, and one action without.
 */
class FeatureFlagTestController
{
    #[Route('/feature-flag-test/flagged-action', name: 'feature_flag_test_flagged_action', methods: ['GET'])]
    #[RequiresFeatureFlag(TestFeatureFlag::NEW_DASHBOARD)]
    public function flaggedAction(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/feature-flag-test/plain-action', name: 'feature_flag_test_plain_action', methods: ['GET'])]
    public function plainAction(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
