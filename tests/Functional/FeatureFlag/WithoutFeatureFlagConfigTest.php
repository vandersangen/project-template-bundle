<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagAudienceRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;
use VanDerSangen\ProjectTemplateBundle\Tests\App\WithoutFeatureFlagsTestKernel;

/**
 * A project that configures no feature flag audiences: the container boots, nothing is on and nothing throws.
 */
class WithoutFeatureFlagConfigTest extends WebTestCase
{
    use FeatureFlagWebTestTrait;

    protected static function getKernelClass(): string
    {
        return WithoutFeatureFlagsTestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->removeAllFeatureFlags();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        self::bootKernel();
        $this->removeAllFeatureFlags();
        $this->connection()->executeStatement("DELETE FROM super_admin_users WHERE username LIKE 'flag-admin-%'");

        parent::tearDown();
    }

    public function testTheKernelBootsWithoutAudiencesAndEverythingIsOff(): void
    {
        self::bootKernel();
        $this->createFeatureFlag('a');
        $container = static::getContainer();

        $this->assertSame([], $container->get(FeatureFlagAudienceRegistry::class)->audiences());

        $featureFlagService = $container->get(FeatureFlagService::class);
        $this->assertFalse($featureFlagService->isOn('a'));
        $this->assertFalse($featureFlagService->isOnFor('a', 'klanten'));
        $this->assertSame([], $featureFlagService->listCidsOn());
        $this->assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(1) FROM feature_flag_audiences'));
    }

    public function testTheListIsEmptyForAGuest(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/api/feature-flags');

        $this->assertResponseIsSuccessful();
        $this->assertSame('{"featureFlags":[]}', $client->getResponse()->getContent());
    }

    public function testTheSuperAdminListShowsTheFlagsWithoutAudiencesAndASwitchIsNotFound(): void
    {
        $client = static::createClient();
        $this->createFeatureFlag('a', 'Alpha');
        $header = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST_USER' => $this->createSuperAdmin()];

        $client->request(Request::METHOD_GET, '/api/super-admin/feature-flags', [], [], $header);
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['featureFlags' => [['cid' => 'a', 'name' => 'Alpha', 'description' => null, 'audiences' => []]]],
            $this->responseData($client),
        );

        $client->request(
            Request::METHOD_PUT,
            '/api/super-admin/feature-flags/a/audiences/klanten',
            [],
            [],
            $header,
            '{"on": true}',
        );
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(1) FROM feature_flag_audiences'));
    }

    public function testARouteWithoutTheAttributeStillAnswers(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/super-admin/login');

        $this->assertResponseIsSuccessful();
    }
}
