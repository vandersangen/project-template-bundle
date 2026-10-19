<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagSetting;

/**
 * Test only: an onFlush listener, like a consumer's audit, that records what the unit of work does with a
 * FeatureFlagSetting, and what a reader on another connection sees of feature_flags at that moment (inside the
 * transaction of switchFlag()).
 */
final class FeatureFlagChangeRecorder
{
    /** @var list<array{0: mixed, 1: mixed}> The change set of "on" of each update. */
    public array $updatesOfOn = [];

    public int $insertions = 0;

    /** @var list<list<array<string, mixed>>> The rows another connection saw, per flush. */
    public array $rowsSeenOutside = [];

    public function __construct(private readonly Connection $outsideConnection)
    {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $unitOfWork = $event->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof FeatureFlagSetting) {
                $this->insertions++;
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof FeatureFlagSetting) {
                $this->updatesOfOn[] = $unitOfWork->getEntityChangeSet($entity)['on'];
            }
        }

        $this->rowsSeenOutside[] = $this->outsideConnection->fetchAllAssociative(
            'SELECT feature_flag, audience, is_on FROM feature_flags',
        );
    }
}
