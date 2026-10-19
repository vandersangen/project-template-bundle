<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;

/**
 * A controller that belongs to a feature flag as a whole.
 */
#[RequiresFeatureFlag(TestFeatureFlag::NIGHTLY_REPORT)]
class FeatureFlagGuardedTestController
{
    #[Route('/feature-flag-test/guarded', name: 'feature_flag_test_guarded', methods: ['GET'])]
    public function guarded(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
