<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use DateTimeImmutable;
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
        $this->removeAllFeatureFlagSettings();
        $this->superAdmin = $this->createSuperAdmin();
    }

    protected function tearDown(): void
    {
        $this->removeAllFeatureFlagSettings();

        parent::tearDown();
    }

    public function testListsEveryFlagOffForEveryAudience(): void
    {
        $this->request(Request::METHOD_GET, self::PATH);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [
                'featureFlags' => [
                    [
                        'key' => 'new_dashboard',
                        'description' => 'Nieuw dashboard',
                        'audiences' => [
                            $this->offAudience('klanten', 'Klanten'),
                            $this->offAudience('medewerkers', 'Medewerkers'),
                        ],
                    ],
                    [
                        'key' => 'nightly_report',
                        'description' => 'Nachtelijk rapport',
                        'audiences' => [
                            $this->offAudience('klanten', 'Klanten'),
                            $this->offAudience('medewerkers', 'Medewerkers'),
                        ],
                    ],
                ],
            ],
            $this->responseData($this->client),
        );
    }

    public function testSwitchingOnIsStoredForThatAudienceAndAnswersTheList(): void
    {
        $this->request(
            Request::METHOD_PUT,
            self::PATH . '/new_dashboard',
            ['audience' => 'medewerkers', 'on' => true],
        );

        $this->assertResponseIsSuccessful();
        $featureFlags = $this->responseData($this->client)['featureFlags'];
        $audiences = array_column($featureFlags[0]['audiences'], null, 'key');
        $this->assertSame('new_dashboard', $featureFlags[0]['key']);
        $this->assertTrue($audiences['medewerkers']['on']);
        $this->assertSame($this->superAdmin, $audiences['medewerkers']['updatedBy']);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $audiences['medewerkers']['updatedAt']));
        $this->assertSame($this->offAudience('klanten', 'Klanten'), $audiences['klanten']);
        $this->assertSame(
            [$this->offAudience('klanten', 'Klanten'), $this->offAudience('medewerkers', 'Medewerkers')],
            $featureFlags[1]['audiences'],
        );

        $row = $this->connection()->fetchAssociative(
            'SELECT feature_flag, audience, is_on, updated_by FROM feature_flags',
        );
        $this->assertSame(
            [
                'feature_flag' => 'new_dashboard',
                'audience' => 'medewerkers',
                'is_on' => 1,
                'updated_by' => $this->superAdmin,
            ],
            $row,
        );
    }

    public function testSwitchingOffAgain(): void
    {
        $this->request(Request::METHOD_PUT, self::PATH . '/new_dashboard', ['audience' => 'klanten', 'on' => true]);
        $this->request(Request::METHOD_PUT, self::PATH . '/new_dashboard', ['audience' => 'klanten', 'on' => false]);

        $this->assertResponseIsSuccessful();
        $audiences = $this->responseData($this->client)['featureFlags'][0]['audiences'];
        $this->assertFalse($audiences[0]['on']);
        $this->assertSame($this->superAdmin, $audiences[0]['updatedBy']);
    }

    public function testARowOfARemovedFlagOrAudienceIsLeftOutOfTheList(): void
    {
        $this->connection()->insert('feature_flags', [
            'feature_flag' => 'removed_flag',
            'audience' => 'klanten',
            'is_on' => 1,
            'updated_at' => '2026-10-19 12:00:00',
            'updated_by' => 'rick',
        ]);
        $this->connection()->insert('feature_flags', [
            'feature_flag' => 'new_dashboard',
            'audience' => 'removed_audience',
            'is_on' => 1,
            'updated_at' => '2026-10-19 12:00:00',
            'updated_by' => 'rick',
        ]);

        $this->request(Request::METHOD_GET, self::PATH);

        $this->assertResponseIsSuccessful();
        $featureFlags = $this->responseData($this->client)['featureFlags'];
        $this->assertSame(['new_dashboard', 'nightly_report'], array_column($featureFlags, 'key'));
        $this->assertSame(['klanten', 'medewerkers'], array_column($featureFlags[0]['audiences'], 'key'));
        $this->assertFalse($featureFlags[0]['audiences'][0]['on']);
    }

    public function testAnUnknownFlagIsNotFound(): void
    {
        $this->request(Request::METHOD_PUT, self::PATH . '/removed_flag', ['audience' => 'klanten', 'on' => true]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertNothingStored();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function invalidSwitches(): iterable
    {
        yield 'audience missing' => [['on' => true], ['audience' => 'Kies voor wie.']];
        yield 'audience unknown' => [['audience' => 'iedereen', 'on' => true], ['audience' => 'Kies voor wie.']];
        yield 'audience no string' => [['audience' => 1, 'on' => true], ['audience' => 'Kies voor wie.']];
        yield 'on missing' => [['audience' => 'klanten'], ['on' => 'Kies aan of uit.']];
        yield 'on no bool' => [['audience' => 'klanten', 'on' => 'ja'], ['on' => 'Kies aan of uit.']];
        yield 'on as number' => [['audience' => 'klanten', 'on' => 1], ['on' => 'Kies aan of uit.']];
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $errors
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSwitches')]
    public function testAnInvalidSwitchIsRefusedAndNothingStored(array $body, array $errors): void
    {
        $this->request(Request::METHOD_PUT, self::PATH . '/new_dashboard', $body);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(['errors' => $errors], $this->responseData($this->client));
        $this->assertNothingStored();
    }

    public function testAMalformedBodyIsABadRequestAndNothingStored(): void
    {
        foreach (['{"audience": "klanten", "on": tru', '', '"klanten"'] as $content) {
            $this->client->request(
                Request::METHOD_PUT,
                self::PATH . '/new_dashboard',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', self::userHeader() => $this->superAdmin],
                $content,
            );

            $this->assertResponseStatusCodeSame(400);
            $this->assertNothingStored();
        }
    }

    public function testAPlainUserIsRefused(): void
    {
        $email = $this->registerUser($this->client)['email'];

        $this->request(Request::METHOD_GET, self::PATH, null, $email);
        $this->assertResponseStatusCodeSame(403);

        $this->request(
            Request::METHOD_PUT,
            self::PATH . '/new_dashboard',
            ['audience' => 'klanten', 'on' => true],
            $email,
        );
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
     * @return array{key: string, description: string, on: false, updatedAt: null, updatedBy: null}
     */
    private function offAudience(string $key, string $description): array
    {
        return ['key' => $key, 'description' => $description, 'on' => false, 'updatedAt' => null, 'updatedBy' => null];
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(1) FROM feature_flags'));
    }
}
