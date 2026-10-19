<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use Symfony\Component\HttpFoundation\Response;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;

/**
 * One action behind a feature flag and one without.
 */
class FlaggedController
{
    #[RequiresFeatureFlag(TestFeatureFlag::NEW_DASHBOARD)]
    public function flagged(): Response
    {
        return new Response();
    }

    public function unflagged(): Response
    {
        return new Response();
    }
}
