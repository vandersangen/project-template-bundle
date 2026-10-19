<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagSettingRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\App\WithoutFeatureFlagsTestKernel;

/**
 * A project that configures no feature flags: the container boots, nothing is on and nothing throws.
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
        $this->connection()->executeStatement('DELETE FROM feature_flags');
        static::getContainer()->get(FeatureFlagService::class)->clearCachedStates();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        self::bootKernel();
        $this->connection()->executeStatement('DELETE FROM feature_flags');
        static::getContainer()->get(FeatureFlagService::class)->clearCachedStates();

        parent::tearDown();
    }

    public function testTheKernelBootsWithoutFlagsOrAudiences(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->assertNull($container->getParameter('project_template.feature_flags.flags'));
        $this->assertNull($container->getParameter('project_template.feature_flags.audiences'));

        $registry = $container->get(FeatureFlagRegistry::class);
        $this->assertSame([], $registry->flags());
        $this->assertSame([], $registry->audiences());

        $this->connection()->insert('feature_flags', [
            'feature_flag' => 'new_dashboard',
            'audience' => 'medewerkers',
            'is_on' => 1,
            'updated_at' => '2026-10-19 12:00:00',
            'updated_by' => 'rick@example.com',
        ]);

        $featureFlagService = $container->get(FeatureFlagService::class);
        $this->assertSame([], $featureFlagService->listFlagsOnFor(TestFeatureFlagAudience::CUSTOMERS));
        $this->assertFalse(
            $featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::STAFF),
        );
        $this->assertFalse($featureFlagService->isOnForBackgroundWork(TestFeatureFlag::NIGHTLY_REPORT));
        $this->assertSame([], $container->get(FeatureFlagSettingRepository::class)->findAllSettings());
    }

    public function testTheDefaultResolverRefusesOnlyWhenAsked(): void
    {
        self::bootKernel();
        $resolver = static::getContainer()->get(FeatureFlagAudienceResolverInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Configure project_template.feature_flags.audiences.');

        $resolver->resolveAudienceOfCurrentRequest();
    }

    public function testTheListOfASignedInUserIsEmpty(): void
    {
        $client = static::createClient();
        $client->request(
            Request::METHOD_POST,
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'email' => 'no-flags-' . uniqid() . '@example.com',
                'password' => 'password123',
                'firstName' => 'No',
                'lastName' => 'Flags',
            ]),
        );
        $token = json_decode((string) $client->getResponse()->getContent(), true)['token'];

        $client->request(
            Request::METHOD_GET,
            '/api/feature-flags',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame('{"featureFlags":[]}', $client->getResponse()->getContent());
    }

    public function testTheSuperAdminListIsEmptyAndASwitchIsNotFound(): void
    {
        $client = static::createClient();
        $superAdmin = $this->createSuperAdmin();
        $header = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST_USER' => $superAdmin];

        $client->request(Request::METHOD_GET, '/api/super-admin/feature-flags', [], [], $header);
        $this->assertResponseIsSuccessful();
        $this->assertSame('{"featureFlags":[]}', $client->getResponse()->getContent());

        $client->request(
            Request::METHOD_PUT,
            '/api/super-admin/feature-flags/anything',
            [],
            [],
            $header,
            '{"audience": "klanten", "on": true}',
        );
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(1) FROM feature_flags'));
    }

    public function testARouteWithoutTheAttributeStillAnswers(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/super-admin/login');

        $this->assertResponseIsSuccessful();
    }
}
