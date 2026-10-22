<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures;

use Symfony\Component\HttpFoundation\Response;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;

/**
 * One action behind a feature flag, one behind two and one without.
 */
class FlaggedController
{
    #[RequiresFeatureFlag('a')]
    public function flagged(): Response
    {
        return new Response();
    }

    #[RequiresFeatureFlag('a')]
    #[RequiresFeatureFlag('b')]
    public function twoFlags(): Response
    {
        return new Response();
    }

    public function unflagged(): Response
    {
        return new Response();
    }
}
