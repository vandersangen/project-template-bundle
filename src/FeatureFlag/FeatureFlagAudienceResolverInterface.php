<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Says to which audiences a signed-in user belongs, so that their switches count for that user.
 *
 * Required as soon as the project configures project_template.feature_flags.audiences (the container does not build
 * without it). A visitor without login is never asked about: the audience with guest: true counts for them.
 */
interface FeatureFlagAudienceResolverInterface
{
    /**
     * @param UserInterface $user The signed-in user.
     *
     * @return list<string> Keys of configured audiences; an unknown key is ignored.
     */
    public function resolveAudiencesOf(UserInterface $user): array;
}
