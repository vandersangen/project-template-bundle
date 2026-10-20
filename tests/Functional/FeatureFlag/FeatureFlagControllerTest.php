<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class FeatureFlagControllerTest extends WebTestCase
{
    use FeatureFlagWebTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->removeAllFeatureFlags();
    }

    protected function tearDown(): void
    {
        $this->removeAllFeatureFlags();
        $this->connection()->executeStatement("DELETE FROM users WHERE email LIKE 'flag-%'");

        parent::tearDown();
    }

    public function testAGuestGetsTheFlagsOnForTheGuestAudience(): void
    {
        $this->createFeatureFlag('a');
        $this->createFeatureFlag('b');

        $this->client->request(Request::METHOD_GET, '/api/feature-flags');
        $this->assertResponseIsSuccessful();
        $this->assertSame(['featureFlags' => []], $this->responseData($this->client));

        $this->featureFlagService()->switchFlag('a', 'gasten', true, 'rick');
        $this->featureFlagService()->switchFlag('b', 'klanten', true, 'rick');

        $this->client->request(Request::METHOD_GET, '/api/feature-flags');
        $this->assertResponseIsSuccessful();
        $this->assertSame(['featureFlags' => ['a']], $this->responseData($this->client));
    }

    public function testASignedInUserGetsTheFlagsOnForTheirAudienceSortedByCid(): void
    {
        $this->createFeatureFlag('c');
        $this->createFeatureFlag('a');
        $this->createFeatureFlag('b');
        $this->featureFlagService()->switchFlag('c', 'klanten', true, 'rick');
        $this->featureFlagService()->switchFlag('a', 'klanten', true, 'rick');
        $this->featureFlagService()->switchFlag('b', 'medewerkers', true, 'rick');

        $token = $this->registerUser($this->client)['token'];
        $this->client->request(
            Request::METHOD_GET,
            '/api/feature-flags',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(['featureFlags' => ['a', 'c']], $this->responseData($this->client));
    }
}
