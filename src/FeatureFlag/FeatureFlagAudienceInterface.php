<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag;

/**
 * For whom a feature flag is switched: each flag has its own switch per audience.
 *
 * The project lists its audiences as a backed enum implementing this interface and names that enum in
 * project_template.feature_flags.audiences.
 */
interface FeatureFlagAudienceInterface
{
    /**
     * The key stored in feature_flags.audience: at most 20 characters, never changed once stored.
     *
     * @return string
     */
    public function key(): string;

    /**
     * What the person switching a flag reads above the switch of this audience.
     *
     * @return string
     */
    public function describe(): string;
}
