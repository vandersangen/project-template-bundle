<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagAudienceRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagAudienceRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudienceResolver;

/**
 * Test only: a FeatureFlagService with the audiences of the test app, a given cache and no signed-in user.
 */
trait FeatureFlagServiceFactory
{
    private function createFeatureFlagService(
        CacheInterface $cache,
        ?FeatureFlagAudienceRepository $featureFlagAudienceRepository = null,
    ): FeatureFlagService {
        return new FeatureFlagService(
            $this->createStub(FeatureFlagRepository::class),
            $featureFlagAudienceRepository ?? $this->createStub(FeatureFlagAudienceRepository::class),
            new FeatureFlagAudienceRegistry([
                'klanten' => ['label' => 'Klanten', 'guest' => false],
                'medewerkers' => ['label' => 'Medewerkers', 'guest' => false],
                'gasten' => ['label' => 'Niet ingelogd', 'guest' => true],
            ]),
            $this->createStub(EntityManagerInterface::class),
            $cache,
            $this->createStub(Security::class),
            new TestFeatureFlagAudienceResolver(),
        );
    }
}
