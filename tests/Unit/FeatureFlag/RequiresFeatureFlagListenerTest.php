<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener\RequiresFeatureFlagListener;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\FlaggedController;

/**
 * The caller is a guest here, so the guest audience (gasten) counts.
 */
class RequiresFeatureFlagListenerTest extends TestCase
{
    use FeatureFlagServiceFactory;

    public function testAControllerWithoutTheAttributeAsksNothing(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $listener = new RequiresFeatureFlagListener($this->createFeatureFlagService($cache));
        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('unflagged'));
    }

    public function testRefusesWithNotFoundWhileTheFlagIsOffForTheCaller(): void
    {
        $listener = new RequiresFeatureFlagListener(
            $this->createFeatureFlagService($this->cacheWithStates(['a' => ['klanten' => true]])),
        );

        $this->expectException(NotFoundHttpException::class);

        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('flagged'));
    }

    public function testLetsThroughWhileTheFlagIsOnForTheCaller(): void
    {
        $listener = new RequiresFeatureFlagListener(
            $this->createFeatureFlagService($this->cacheWithStates(['a' => ['gasten' => true]])),
        );

        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('flagged'));

        $this->addToAssertionCount(1);
    }

    public function testTwoAttributesRefuseWhileOneOfTheFlagsIsOff(): void
    {
        $listener = new RequiresFeatureFlagListener(
            $this->createFeatureFlagService($this->cacheWithStates(['a' => ['gasten' => true]])),
        );

        $this->expectException(NotFoundHttpException::class);

        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('twoFlags'));
    }

    public function testTwoAttributesLetThroughWhileBothFlagsAreOn(): void
    {
        $listener = new RequiresFeatureFlagListener(
            $this->createFeatureFlagService(
                $this->cacheWithStates(['a' => ['gasten' => true], 'b' => ['gasten' => true]]),
            ),
        );

        $listener->refuseWhileFlagIsOffForCaller($this->controllerEvent('twoFlags'));

        $this->addToAssertionCount(1);
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
