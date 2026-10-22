<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagAudienceRepository;

class FeatureFlagServiceCacheTest extends TestCase
{
    use FeatureFlagServiceFactory;

    private const string CACHE_KEY = 'project_template_feature_flag_states';

    public function testTheStatesComeFromTheRepositoryAndExpireAfterAnHourAsABackstop(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $item->expects($this->once())->method('expiresAfter')->with(3600);
        $repository = $this->createMock(FeatureFlagAudienceRepository::class);
        $repository->expects($this->once())->method('findAllStates')->willReturn(['a' => ['klanten' => true]]);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            static fn (string $key, callable $callback): mixed => $callback($item),
        );

        $featureFlagService = $this->createFeatureFlagService($cache, $repository);

        $this->assertTrue($featureFlagService->isOnFor('a', 'klanten'));
    }

    public function testTenChecksReadTheCacheOnce(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('get')->with(self::CACHE_KEY)->willReturn(['a' => ['klanten' => true]]);
        $featureFlagService = $this->createFeatureFlagService($cache);

        for ($check = 0; $check < 10; $check++) {
            $this->assertTrue($featureFlagService->isOnFor('a', 'klanten'));
        }

        $this->assertFalse($featureFlagService->isOn('a'));
        $this->assertSame([], $featureFlagService->listCidsOn());
    }

    public function testAfterAResetTheCacheIsReadAgain(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->exactly(2))->method('get')->willReturn(['a' => ['gasten' => true]]);
        $cache->expects($this->never())->method('delete');
        $featureFlagService = $this->createFeatureFlagService($cache);

        $this->assertTrue($featureFlagService->isOn('a'));
        $featureFlagService->reset();
        $this->assertTrue($featureFlagService->isOn('a'));
    }

    public function testClearingTheCachedStatesDeletesTheItemAndTheCopyInMemory(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->exactly(2))->method('get')->willReturn(['a' => ['gasten' => true]], []);
        $cache->expects($this->once())->method('delete')->with(self::CACHE_KEY);
        $featureFlagService = $this->createFeatureFlagService($cache);

        $this->assertTrue($featureFlagService->isOn('a'));
        $featureFlagService->clearCachedStates();
        $this->assertFalse($featureFlagService->isOn('a'));
    }

    public function testAnAudienceThatIsNotConfiguredIsNeverReadFromTheCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');
        $featureFlagService = $this->createFeatureFlagService($cache);

        $this->assertFalse($featureFlagService->isOnFor('a', 'onbekend'));
    }
}
