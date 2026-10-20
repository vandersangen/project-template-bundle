<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Attribute\RequiresFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * Refuses a route of a feature flag that is off for the caller with 404, so the caller cannot tell it exists. A
 * controller without #[RequiresFeatureFlag] is left alone: no cache, no query.
 *
 * It runs on kernel.controller, before #[IsGranted] (kernel.controller_arguments), so a guest gets 404 and not 401 on a
 * route that is off. An access_control rule of the firewall runs earlier still, on kernel.request.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'refuseWhileFlagIsOffForCaller')]
final readonly class RequiresFeatureFlagListener
{
    public function __construct(private FeatureFlagService $featureFlagService)
    {
    }

    /**
     * @param ControllerEvent $event The controller about to be called.
     *
     * @throws NotFoundHttpException    While a required flag is off for the caller.
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function refuseWhileFlagIsOffForCaller(ControllerEvent $event): void
    {
        /** @var list<RequiresFeatureFlag> $requiredFlags */
        $requiredFlags = $event->getAttributes(RequiresFeatureFlag::class);

        foreach ($requiredFlags as $requiresFeatureFlag) {
            if (!$this->featureFlagService->isOn($requiresFeatureFlag->cid)) {
                throw new NotFoundHttpException();
            }
        }
    }
}
