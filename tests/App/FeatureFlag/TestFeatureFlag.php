<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag;

use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;

/**
 * The flags of the test app, configured in project_template.feature_flags.flags.
 */
enum TestFeatureFlag: string implements FeatureFlagInterface
{
    case NEW_DASHBOARD = 'new_dashboard';
    case NIGHTLY_REPORT = 'nightly_report';

    public function key(): string
    {
        return $this->value;
    }

    public function describe(): string
    {
        return match ($this) {
            self::NEW_DASHBOARD => 'Nieuw dashboard',
            self::NIGHTLY_REPORT => 'Nachtelijk rapport',
        };
    }

    public function backgroundAudience(): FeatureFlagAudienceInterface
    {
        return match ($this) {
            self::NEW_DASHBOARD => TestFeatureFlagAudience::CUSTOMERS,
            self::NIGHTLY_REPORT => TestFeatureFlagAudience::STAFF,
        };
    }
}
