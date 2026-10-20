<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag;

use Symfony\Component\Security\Core\User\UserInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;

/**
 * The audiences of a signed-in user of the test app: a super admin is staff, an identifier with "+beide" is both, one
 * with "+onbekend" gets an unknown key and the guest key, everyone else is a customer.
 */
final readonly class TestFeatureFlagAudienceResolver implements FeatureFlagAudienceResolverInterface
{
    /**
     * @param UserInterface $user The signed-in user.
     *
     * @return list<string>
     */
    public function resolveAudiencesOf(UserInterface $user): array
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return ['medewerkers'];
        }

        if (str_contains($user->getUserIdentifier(), '+beide')) {
            return ['klanten', 'medewerkers'];
        }

        if (str_contains($user->getUserIdentifier(), '+onbekend')) {
            return ['onbekend', 'gasten'];
        }

        return ['klanten'];
    }
}
