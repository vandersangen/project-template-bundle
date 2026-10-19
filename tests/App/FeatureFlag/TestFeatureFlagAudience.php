<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;

/**
 * The audiences of the test app, configured in project_template.feature_flags.audiences.
 */
enum TestFeatureFlagAudience: string implements FeatureFlagAudienceInterface
{
    case CUSTOMERS = 'klanten';
    case STAFF = 'medewerkers';

    public function key(): string
    {
        return $this->value;
    }

    public function describe(): string
    {
        return match ($this) {
            self::CUSTOMERS => 'Klanten',
            self::STAFF => 'Medewerkers',
        };
    }
}
