<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;

/**
 * A controller that belongs to a feature flag as a whole, with one action that needs a second flag as well.
 */
#[RequiresFeatureFlag('b')]
class FeatureFlagGuardedTestController
{
    #[Route('/feature-flag-test/guarded', name: 'feature_flag_test_guarded', methods: ['GET'])]
    public function guarded(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/feature-flag-test/guarded-twice', name: 'feature_flag_test_guarded_twice', methods: ['GET'])]
    #[RequiresFeatureFlag('a')]
    public function guardedTwice(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
