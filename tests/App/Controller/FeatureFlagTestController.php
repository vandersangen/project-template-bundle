<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;

/**
 * One action behind a feature flag, one behind a feature flag and a login, and one without either, on a controller
 * whose class carries no flag.
 */
class FeatureFlagTestController
{
    #[Route('/feature-flag-test/flagged-action', name: 'feature_flag_test_flagged_action', methods: ['GET'])]
    #[RequiresFeatureFlag('a')]
    public function flaggedAction(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/feature-flag-test/flagged-and-granted', name: 'feature_flag_test_flagged_and_granted', methods: ['GET'])]
    #[RequiresFeatureFlag('a')]
    #[IsGranted('ROLE_USER')]
    public function flaggedAndGrantedAction(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/feature-flag-test/plain-action', name: 'feature_flag_test_plain_action', methods: ['GET'])]
    public function plainAction(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
