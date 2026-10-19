<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Cache\InvalidArgumentException;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagSetting;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * A setting of a feature flag changed, for whichever audience: drop the cached states, so the next check reads the
 * database. This also covers a change through the ORM that does not go through FeatureFlagService.
 */
#[AsEntityListener(event: Events::postPersist, method: 'clearCachedStates', entity: FeatureFlagSetting::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'clearCachedStates', entity: FeatureFlagSetting::class)]
#[AsEntityListener(event: Events::postRemove, method: 'clearCachedStates', entity: FeatureFlagSetting::class)]
final readonly class FeatureFlagCacheInvalidationListener
{
    public function __construct(private FeatureFlagService $featureFlagService)
    {
    }

    /**
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function clearCachedStates(): void
    {
        $this->featureFlagService->clearCachedStates();
    }
}
