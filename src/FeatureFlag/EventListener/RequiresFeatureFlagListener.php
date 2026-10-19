<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * Refuses a route of a feature flag that is off for the caller's audience with 404, so the caller cannot tell it
 * exists. A controller without #[RequiresFeatureFlag] is left alone: no resolver, no cache, no query.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'refuseWhileFlagIsOffForCaller')]
final readonly class RequiresFeatureFlagListener
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
        private FeatureFlagAudienceResolverInterface $featureFlagAudienceResolver,
    ) {
    }

    /**
     * @param ControllerEvent $event The controller about to be called.
     *
     * @throws NotFoundHttpException    While a required flag is off for the caller's audience.
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function refuseWhileFlagIsOffForCaller(ControllerEvent $event): void
    {
        /** @var list<RequiresFeatureFlag> $requiredFlags */
        $requiredFlags = $event->getAttributes(RequiresFeatureFlag::class);

        if ($requiredFlags === []) {
            return;
        }

        $audience = $this->featureFlagAudienceResolver->resolveAudienceOfCurrentRequest();

        foreach ($requiredFlags as $requiresFeatureFlag) {
            if (!$this->featureFlagService->isOnFor($requiresFeatureFlag->featureFlag, $audience)) {
                throw new NotFoundHttpException();
            }
        }
    }
}
