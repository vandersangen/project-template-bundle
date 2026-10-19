<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;

class FeatureFlagControllerTest extends WebTestCase
{
    use FeatureFlagWebTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->removeAllFeatureFlagSettings();
    }

    protected function tearDown(): void
    {
        $this->removeAllFeatureFlagSettings();

        parent::tearDown();
    }

    public function testListsNothingWhileNoFlagIsOn(): void
    {
        $this->requestListAsUser();

        $this->assertResponseIsSuccessful();
        $this->assertSame(['featureFlags' => []], $this->responseData($this->client));
    }

    public function testListsTheKeysOfTheFlagsOnForTheAudienceOfTheCaller(): void
    {
        $this->featureFlagService()->switchFlag(
            TestFeatureFlag::NIGHTLY_REPORT,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick',
        );
        $this->featureFlagService()->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::STAFF,
            true,
            'rick',
        );

        $this->requestListAsUser();

        $this->assertResponseIsSuccessful();
        $this->assertSame(['featureFlags' => ['nightly_report']], $this->responseData($this->client));
    }

    public function testRefusesACallerWhoIsNotSignedIn(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/feature-flags');

        $this->assertResponseStatusCodeSame(401);
    }

    private function requestListAsUser(): void
    {
        $token = $this->registerUser($this->client)['token'];
        $this->client->request(
            Request::METHOD_GET,
            '/api/feature-flags',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );
    }
}
