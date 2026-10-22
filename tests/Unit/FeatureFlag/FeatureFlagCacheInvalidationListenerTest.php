<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Contracts\Cache\CacheInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener\FeatureFlagCacheInvalidationListener;

class FeatureFlagCacheInvalidationListenerTest extends TestCase
{
    use FeatureFlagServiceFactory;

    public function testAFlushWithoutAFeatureFlagChangeClearsNothing(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('delete');
        $listener = new FeatureFlagCacheInvalidationListener($this->createFeatureFlagService($cache));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager(updates: [new stdClass()])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
        $listener->clearCachedStatesWhenPending();
    }

    public function testAChangedSwitchIsClearedAfterAFlushOutsideATransaction(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete');
        $listener = new FeatureFlagCacheInvalidationListener($this->createFeatureFlagService($cache));
        $switch = new FeatureFlagAudience(new FeatureFlag(), 'klanten', true, 'rick');

        $listener->onFlush(new OnFlushEventArgs($this->entityManager(updates: [$switch])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager()));
        $listener->clearCachedStatesWhenPending();
    }

    public function testAnInsertedFlagInsideATransactionIsClearedOnceWhenTheProcessOrMessageEnds(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete');
        $listener = new FeatureFlagCacheInvalidationListener($this->createFeatureFlagService($cache));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager(insertions: [new FeatureFlag()])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager(transactionActive: true)));
        $listener->clearCachedStatesWhenPending();
        $listener->clearCachedStatesWhenPending();
    }

    public function testADeletedFlagInsideATransactionIsClearedWhenTheProcessOrMessageEnds(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete');
        $listener = new FeatureFlagCacheInvalidationListener($this->createFeatureFlagService($cache));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager(deletions: [new FeatureFlag()])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager(transactionActive: true)));
        $listener->clearCachedStatesWhenPending();
    }

    public function testAResetClearsWhatIsPending(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete');
        $listener = new FeatureFlagCacheInvalidationListener($this->createFeatureFlagService($cache));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager(insertions: [new FeatureFlag()])));
        $listener->postFlush(new PostFlushEventArgs($this->entityManager(transactionActive: true)));
        $listener->reset();
        $listener->reset();
    }

    /**
     * @param object[] $insertions
     * @param object[] $updates
     * @param object[] $deletions
     */
    private function entityManager(
        array $insertions = [],
        array $updates = [],
        array $deletions = [],
        bool $transactionActive = false,
    ): EntityManagerInterface {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn($deletions);
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn($transactionActive);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
