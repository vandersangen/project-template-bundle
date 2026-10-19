<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagSettingRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;

class FeatureFlagServiceCacheTest extends TestCase
{
    public function testTheCachedStatesExpireAfterAnHourAsABackstop(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(3600);
        $repository = $this->createStub(FeatureFlagSettingRepository::class);
        $repository->method('findStoredStates')->willReturn(['new_dashboard' => ['klanten' => true]]);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            static fn (string $key, callable $callback): mixed => $callback($item),
        );

        $featureFlagService = new FeatureFlagService(
            $repository,
            new FeatureFlagRegistry(TestFeatureFlag::class, TestFeatureFlagAudience::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ManagerRegistry::class),
            $cache,
        );

        $this->assertTrue(
            $featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::CUSTOMERS),
        );
    }
}
