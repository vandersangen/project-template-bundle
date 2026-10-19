<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener\RequiresFeatureFlagListener;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagSettingRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\FlaggedController;

class RequiresFeatureFlagListenerTest extends TestCase
{
    public function testAControllerWithoutTheAttributeAsksNothing(): void
    {
        $resolver = $this->createMock(FeatureFlagAudienceResolverInterface::class);
        $resolver->expects($this->never())->method('resolveAudienceOfCurrentRequest');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $listener = new RequiresFeatureFlagListener($this->featureFlagService($cache), $resolver);
        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('unflagged'));
    }

    public function testRefusesWithNotFoundWhileTheFlagIsOffForTheCaller(): void
    {
        $listener = new RequiresFeatureFlagListener(
            $this->featureFlagService($this->cacheWithStates([])),
            $this->resolverOf(TestFeatureFlagAudience::CUSTOMERS),
        );

        $this->expectException(NotFoundHttpException::class);

        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('flagged'));
    }

    public function testLetsThroughWhileTheFlagIsOnForTheCaller(): void
    {
        $listener = new RequiresFeatureFlagListener(
            $this->featureFlagService($this->cacheWithStates(['new_dashboard' => ['klanten' => true]])),
            $this->resolverOf(TestFeatureFlagAudience::CUSTOMERS),
        );

        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('flagged'));

        $this->addToAssertionCount(1);
    }

    private function featureFlagService(CacheInterface $cache): FeatureFlagService
    {
        return new FeatureFlagService(
            $this->createStub(FeatureFlagSettingRepository::class),
            new FeatureFlagRegistry(TestFeatureFlag::class, TestFeatureFlagAudience::class),
            $this->createStub(EntityManagerInterface::class),
            $cache,
        );
    }

    /**
     * @param array<string, array<string, bool>> $states
     */
    private function cacheWithStates(array $states): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn($states);

        return $cache;
    }

    private function resolverOf(TestFeatureFlagAudience $audience): FeatureFlagAudienceResolverInterface
    {
        $resolver = $this->createStub(FeatureFlagAudienceResolverInterface::class);
        $resolver->method('resolveAudienceOfCurrentRequest')->willReturn($audience);

        return $resolver;
    }

    private function controllerEvent(string $action): ControllerEvent
    {
        return new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            [new FlaggedController(), $action],
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
