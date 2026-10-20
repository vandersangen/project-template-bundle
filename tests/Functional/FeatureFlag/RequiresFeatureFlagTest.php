<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use VanDerSangen\ProjectTemplateBundle\Tests\App\Security\TestApiUserAuthenticator;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;

/**
 * #[RequiresFeatureFlag] on an action and on a controller class. The test app's resolver makes a plain user a
 * customer (klanten) and a super admin staff (medewerkers); a guest is of the guest audience (gasten).
 */
class RequiresFeatureFlagTest extends WebTestCase
{
    use FeatureFlagWebTestTrait;

    private KernelBrowser $client;

    /** @var list<string> */
    private array $createdUserEmails = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->removeAllFeatureFlags();
        $this->createFeatureFlag('a');
        $this->createFeatureFlag('b');
    }

    protected function tearDown(): void
    {
        $this->removeAllFeatureFlags();

        foreach ($this->createdUserEmails as $email) {
            $this->connection()->executeStatement('DELETE FROM users WHERE email = ?', [$email]);
        }

        $this->connection()->executeStatement("DELETE FROM super_admin_users WHERE username LIKE 'flag-admin-%'");

        parent::tearDown();
    }

    public function testAnActionWithTheAttributeIsNotFoundWhileTheFlagIsOffForEveryone(): void
    {
        $this->requestAsGuest('/feature-flag-test/flagged-action');
        $this->assertResponseStatusCodeSame(404);

        $this->requestAs($this->createCustomer(), '/feature-flag-test/flagged-action');
        $this->assertResponseStatusCodeSame(404);

        $this->requestAs($this->createSuperAdmin(), '/feature-flag-test/flagged-action');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnActionWithTheAttributeAnswersOnlyForTheAudienceItIsOnFor(): void
    {
        $this->featureFlagService()->switchFlag('a', 'klanten', true, 'rick');

        $this->requestAs($this->createCustomer(), '/feature-flag-test/flagged-action');
        $this->assertResponseIsSuccessful();

        $this->requestAsGuest('/feature-flag-test/flagged-action');
        $this->assertResponseStatusCodeSame(404);

        $this->requestAs($this->createSuperAdmin(), '/feature-flag-test/flagged-action');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAGuestPassesWhileTheFlagIsOnForTheGuestAudience(): void
    {
        $this->featureFlagService()->switchFlag('a', 'gasten', true, 'rick');

        $this->requestAsGuest('/feature-flag-test/flagged-action');
        $this->assertResponseIsSuccessful();

        $this->requestAs($this->createCustomer(), '/feature-flag-test/flagged-action');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnActionWithoutTheAttributeNextToItAlwaysAnswers(): void
    {
        $this->requestAsGuest('/feature-flag-test/plain-action');

        $this->assertResponseIsSuccessful();
    }

    public function testAControllerWithTheAttributeFollowsTheFlagOfTheClass(): void
    {
        $this->featureFlagService()->switchFlag('a', 'gasten', true, 'rick');

        $this->requestAsGuest('/feature-flag-test/guarded');
        $this->assertResponseStatusCodeSame(404);

        $this->featureFlagService()->switchFlag('b', 'gasten', true, 'rick');

        $this->requestAsGuest('/feature-flag-test/guarded');
        $this->assertResponseIsSuccessful();
    }

    public function testTwoAttributesNeedBothFlagsOn(): void
    {
        $this->featureFlagService()->switchFlag('b', 'gasten', true, 'rick');

        $this->requestAsGuest('/feature-flag-test/guarded-twice');
        $this->assertResponseStatusCodeSame(404);

        $this->featureFlagService()->switchFlag('a', 'gasten', true, 'rick');

        $this->requestAsGuest('/feature-flag-test/guarded-twice');
        $this->assertResponseIsSuccessful();
    }

    public function testAGuestOnAnActionThatAlsoNeedsALoginIsNotFoundWhileTheFlagIsOffAndRefusedWhileItIsOn(): void
    {
        $this->requestAsGuest('/feature-flag-test/flagged-and-granted');
        $this->assertResponseStatusCodeSame(404);

        $this->featureFlagService()->switchFlag('a', 'gasten', true, 'rick');

        $this->requestAsGuest('/feature-flag-test/flagged-and-granted');
        $this->assertResponseStatusCodeSame(401);
    }

    private function requestAsGuest(string $path): void
    {
        $this->client->request(Request::METHOD_GET, $path);
    }

    private function requestAs(string $userIdentifier, string $path): void
    {
        $this->client->request(
            Request::METHOD_GET,
            $path,
            [],
            [],
            ['HTTP_' . str_replace('-', '_', strtoupper(TestApiUserAuthenticator::HEADER)) => $userIdentifier],
        );
    }

    private function createCustomer(): string
    {
        $email = 'flag-' . uniqid() . '@example.com';
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-used');
        $user->setFirstName('Flag');
        $user->setLastName('User');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();
        $this->createdUserEmails[] = $email;

        return $email;
    }
}
