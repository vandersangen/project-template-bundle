<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Service\ResetInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

/**
 * A flag or a switch changed through the ORM, also outside FeatureFlagService: drop the cached states, but only once
 * the change is committed, so no other process caches the old states again in between.
 *
 * A flush outside a transaction is committed when it ends (postFlush). A flush inside a transaction of the caller (the
 * fixtures loader, a project wrapping its own work) is cleared when the request, command or message ends, or at the
 * next kernel.reset. The worker events come before the reset of the services (WorkerRunningEvent, priority -1024).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'clearCachedStatesWhenPending')]
#[AsEventListener(event: ConsoleEvents::TERMINATE, method: 'clearCachedStatesWhenPending')]
#[AsEventListener(event: WorkerMessageHandledEvent::class, method: 'clearCachedStatesWhenPending')]
#[AsEventListener(event: WorkerMessageFailedEvent::class, method: 'clearCachedStatesWhenPending')]
final class FeatureFlagCacheInvalidationListener implements ResetInterface
{
    /** Whether a flushed change of a flag or switch still waits for its cache clear. */
    private bool $pending = false;

    public function __construct(private readonly FeatureFlagService $featureFlagService)
    {
    }

    /**
     * @param OnFlushEventArgs $event The flush about to be written.
     */
    public function onFlush(OnFlushEventArgs $event): void
    {
        $unitOfWork = $event->getObjectManager()->getUnitOfWork();
        $changedEntities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ];

        foreach ($changedEntities as $entity) {
            if ($entity instanceof FeatureFlag || $entity instanceof FeatureFlagAudience) {
                $this->pending = true;

                return;
            }
        }
    }

    /**
     * @param PostFlushEventArgs $event The flush that was written.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($event->getObjectManager()->getConnection()->isTransactionActive()) {
            return;
        }

        $this->clearCachedStatesWhenPending();
    }

    /**
     * At the end of a request, command or message, when no transaction is left open.
     *
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function clearCachedStatesWhenPending(): void
    {
        if (!$this->pending) {
            return;
        }

        $this->featureFlagService->clearCachedStates();
        $this->pending = false;
    }

    /**
     * @throws InvalidArgumentException When the cache refuses the key.
     */
    public function reset(): void
    {
        $this->clearCachedStatesWhenPending();
    }
}
