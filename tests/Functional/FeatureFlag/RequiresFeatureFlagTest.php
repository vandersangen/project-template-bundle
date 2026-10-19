<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;

/**
 * #[RequiresFeatureFlag] on an action and on a controller class. The test app's resolver is the bundle default,
 * so every request is of the first audience (klanten).
 */
class RequiresFeatureFlagTest extends WebTestCase
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

    public function testAnActionWithTheAttributeIsNotFoundWhileTheFlagIsOff(): void
    {
        $this->client->request(Request::METHOD_GET, '/feature-flag-test/flagged-action');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnActionWithTheAttributeIsNotFoundWhileTheFlagIsOnForAnotherAudienceOnly(): void
    {
        $this->featureFlagService()->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::STAFF,
            true,
            'rick',
        );

        $this->client->request(Request::METHOD_GET, '/feature-flag-test/flagged-action');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnActionWithTheAttributeAnswersWhileTheFlagIsOn(): void
    {
        $this->featureFlagService()->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick',
        );

        $this->client->request(Request::METHOD_GET, '/feature-flag-test/flagged-action');

        $this->assertResponseIsSuccessful();
    }

    public function testAnActionWithoutTheAttributeNextToItAlwaysAnswers(): void
    {
        $this->client->request(Request::METHOD_GET, '/feature-flag-test/plain-action');

        $this->assertResponseIsSuccessful();
    }

    public function testAControllerWithTheAttributeIsNotFoundWhileTheFlagIsOff(): void
    {
        $this->featureFlagService()->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick',
        );

        $this->client->request(Request::METHOD_GET, '/feature-flag-test/guarded');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAControllerWithTheAttributeAnswersWhileTheFlagIsOn(): void
    {
        $this->featureFlagService()->switchFlag(
            TestFeatureFlag::NIGHTLY_REPORT,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick',
        );

        $this->client->request(Request::METHOD_GET, '/feature-flag-test/guarded');

        $this->assertResponseIsSuccessful();
    }
}
