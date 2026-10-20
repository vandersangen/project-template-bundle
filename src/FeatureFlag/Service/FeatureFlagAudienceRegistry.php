<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service;

/**
 * The audiences of the project, from project_template.feature_flags.audiences; checked when the container is built.
 *
 * Without that config there are no audiences: every check is then off and every list empty.
 */
final readonly class FeatureFlagAudienceRegistry
{
    /**
     * @param array<string, array{label: string, guest: bool}> $audiences The configured audiences, by key.
     */
    public function __construct(private array $audiences)
    {
    }

    /**
     * @return array<string, array{label: string, guest: bool}> The audiences by key, in config order.
     */
    public function audiences(): array
    {
        return $this->audiences;
    }

    /**
     * @param string $audience The key of the audience.
     *
     * @return bool
     */
    public function hasAudience(string $audience): bool
    {
        return isset($this->audiences[$audience]);
    }

    /**
     * @return string|null The key of the audience with guest: true, or null when no audience is for guests.
     */
    public function findGuestAudience(): ?string
    {
        foreach ($this->audiences as $key => $audience) {
            if ($audience['guest']) {
                return $key;
            }
        }

        return null;
    }
}
