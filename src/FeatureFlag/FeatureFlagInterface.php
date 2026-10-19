<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag;

/**
 * A feature flag of the project: a part of the application that is switched on or off per audience.
 *
 * The project lists its flags as a backed enum implementing this interface and names that enum in
 * project_template.feature_flags.flags. A flag nobody switched yet is off for everyone.
 */
interface FeatureFlagInterface
{
    /**
     * The key stored in feature_flags.feature_flag: at most 50 characters, never changed once stored.
     *
     * @return string
     */
    public function key(): string;

    /**
     * What the person switching the flag reads next to it.
     *
     * @return string
     */
    public function describe(): string;

    /**
     * Whose switch counts for work without a signed-in user, such as a cron or a queue handler.
     *
     * @return FeatureFlagAudienceInterface
     */
    public function backgroundAudience(): FeatureFlagAudienceInterface;
}
