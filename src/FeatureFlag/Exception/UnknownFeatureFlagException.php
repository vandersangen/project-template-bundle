<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Exception;

use DomainException;

/**
 * A flag or audience was named that the project does not have: no flag with that cid, or no configured audience
 * with that key.
 */
final class UnknownFeatureFlagException extends DomainException
{
    /**
     * @param string $cid The cid that was named.
     *
     * @return self
     */
    public static function unknownFlag(string $cid): self
    {
        return new self(sprintf('No feature flag with cid "%s".', $cid));
    }

    /**
     * @param string $audience The audience key that was named.
     *
     * @return self
     */
    public static function unknownAudience(string $audience): self
    {
        return new self(sprintf('No feature flag audience "%s" is configured.', $audience));
    }
}
