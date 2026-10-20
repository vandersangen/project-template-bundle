<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use VanDerSangen\ProjectTemplateBundle\Tests\App\Security\TestApiUserAuthenticator;

class SuperAdminFeatureFlagControllerTest extends WebTestCase
{
    use FeatureFlagWebTestTrait;

    private const string PATH = '/api/super-admin/feature-flags';

    private KernelBrowser $client;
    private string $superAdmin;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->removeAllFeatureFlags();
        $this->createFeatureFlag('b', 'Beta');
        $this->createFeatureFlag('a', 'Alpha');
        $this->superAdmin = $this->createSuperAdmin();
    }

    protected function tearDown(): void
    {
        $this->removeAllFeatureFlags();
        $this->connection()->executeStatement("DELETE FROM users WHERE email LIKE 'flag-%'");
        $this->connection()->executeStatement("DELETE FROM super_admin_users WHERE username LIKE 'flag-admin-%'");

        parent::tearDown();
    }

    public function testListsEveryFlagSortedByCidWithEveryAudienceOff(): void
    {
        $this->request(Request::METHOD_GET, self::PATH);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [
                'featureFlags' => [
                    [
                        'cid' => 'a',
                        'name' => 'Alpha',
                        'description' => null,
                        'audiences' => $this->offAudiences(),
                    ],
                    [
                        'cid' => 'b',
                        'name' => 'Beta',
                        'description' => null,
                        'audiences' => $this->offAudiences(),
                    ],
                ],
            ],
            $this->responseData($this->client),
        );
    }

    public function testSwitchingOnIsStoredForThatAudienceAndAnswersTheList(): void
    {
        $this->request(Request::METHOD_PUT, self::PATH . '/a/audiences/medewerkers', ['on' => true]);

        $this->assertResponseIsSuccessful();
        $featureFlags = $this->responseData($this->client)['featureFlags'];
        $audiences = array_column($featureFlags[0]['audiences'], null, 'key');
        $this->assertSame('a', $featureFlags[0]['cid']);
        $this->assertTrue($audiences['medewerkers']['on']);
        $this->assertSame($this->superAdmin, $audiences['medewerkers']['updatedBy']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $audiences['medewerkers']['updatedAt']));
        $this->assertSame($this->offAudiences()[0], $audiences['klanten']);
        $this->assertSame($this->offAudiences(), $featureFlags[1]['audiences']);
        $this->assertTrue($this->featureFlagService()->isOnFor('a', 'medewerkers'));
    }

    public function testSwitchingOffAgain(): void
    {
        $this->request(Request::METHOD_PUT, self::PATH . '/a/audiences/klanten', ['on' => true]);
        $this->request(Request::METHOD_PUT, self::PATH . '/a/audiences/klanten', ['on' => false]);

        $this->assertResponseIsSuccessful();
        $audiences = $this->responseData($this->client)['featureFlags'][0]['audiences'];
        $this->assertFalse($audiences[0]['on']);
        $this->assertSame($this->superAdmin, $audiences[0]['updatedBy']);
        $this->assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(1) FROM feature_flag_audiences'));
    }

    public function testAnUnknownFlagOrAudienceIsNotFound(): void
    {
        $body = ['on' => true];
        $notFound = ['error' => 'Deze feature flag of doelgroep bestaat niet.'];

        $this->request(Request::METHOD_PUT, self::PATH . '/removed/audiences/klanten', $body);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame($notFound, $this->responseData($this->client));

        $this->request(Request::METHOD_PUT, self::PATH . '/a/audiences/iedereen', $body);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame($notFound, $this->responseData($this->client));

        $this->request(Request::METHOD_PUT, self::PATH . '/removed/audiences/klanten');
        $this->assertResponseStatusCodeSame(404);
        $this->assertNothingStored();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'on missing' => ['{}'];
        yield 'on no bool' => ['{"on": "yes"}'];
        yield 'on as number' => ['{"on": 1}'];
        yield 'not json' => ['{"on": tru'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidBodies')]
    public function testAnInvalidBodyIsRefusedAndNothingStored(string $content): void
    {
        $this->client->request(
            Request::METHOD_PUT,
            self::PATH . '/a/audiences/klanten',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', self::userHeader() => $this->superAdmin],
            $content,
        );

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(['errors' => ['on' => 'Kies aan of uit.']], $this->responseData($this->client));
        $this->assertNothingStored();
    }

    public function testAPlainUserIsRefused(): void
    {
        $email = $this->registerUser($this->client)['email'];

        $this->request(Request::METHOD_GET, self::PATH, null, $email);
        $this->assertResponseStatusCodeSame(403);

        $this->request(Request::METHOD_PUT, self::PATH . '/a/audiences/klanten', ['on' => true], $email);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNothingStored();
    }

    public function testACallerWhoIsNotSignedInIsRefused(): void
    {
        $this->client->request(Request::METHOD_GET, self::PATH);

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * @param string                    $method The HTTP method.
     * @param string                    $path   The path.
     * @param array<string, mixed>|null $body   The JSON body, or null without one.
     * @param string|null               $user   The user to sign in as; the super admin when null.
     */
    private function request(string $method, string $path, ?array $body = null, ?string $user = null): void
    {
        $this->client->request(
            $method,
            $path,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                self::userHeader() => $user ?? $this->superAdmin,
            ],
            $body === null ? null : (string) json_encode($body),
        );
    }

    private static function userHeader(): string
    {
        return 'HTTP_' . str_replace('-', '_', strtoupper(TestApiUserAuthenticator::HEADER));
    }

    /**
     * @return list<array{key: string, label: string, guest: bool, on: false, updatedAt: null, updatedBy: null}>
     */
    private function offAudiences(): array
    {
        return [
            $this->offAudience('klanten', 'Klanten', false),
            $this->offAudience('medewerkers', 'Medewerkers', false),
            $this->offAudience('gasten', 'Niet ingelogd', true),
        ];
    }

    /**
     * @return array{key: string, label: string, guest: bool, on: false, updatedAt: null, updatedBy: null}
     */
    private function offAudience(string $key, string $label, bool $guest): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'guest' => $guest,
            'on' => false,
            'updatedAt' => null,
            'updatedBy' => null,
        ];
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(1) FROM feature_flag_audiences'));
    }
}
