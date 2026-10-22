<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;

/**
 * Helpers for the web tests of the feature flags: a clean table, a signed-in user and a super admin.
 */
trait FeatureFlagWebTestTrait
{
    /**
     * Deletes every flag, which empties feature_flag_audiences through its cascade, and clears the cached states.
     */
    private function removeAllFeatureFlags(): void
    {
        $this->connection()->executeStatement('DELETE FROM feature_flags');
        static::getContainer()->get(FeatureFlagService::class)->clearCachedStates();
    }

    private function createFeatureFlag(string $cid, string $name = 'Test'): FeatureFlag
    {
        $flag = new FeatureFlag();
        $flag->setCid($cid);
        $flag->setName($name);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($flag);
        $entityManager->flush();

        return $flag;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getConnection();
    }

    private function featureFlagService(): FeatureFlagService
    {
        return static::getContainer()->get(FeatureFlagService::class);
    }

    /**
     * @return array{email: string, token: string}
     */
    private function registerUser(KernelBrowser $client): array
    {
        $email = 'flag-' . uniqid() . '@example.com';
        $client->request(
            Request::METHOD_POST,
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'email' => $email,
                'password' => 'password123',
                'firstName' => 'Flag',
                'lastName' => 'User',
            ]),
        );
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        return ['email' => $email, 'token' => (string) $data['token']];
    }

    private function createSuperAdmin(): string
    {
        $username = 'flag-admin-' . uniqid();
        $superAdmin = new SuperAdminUser();
        $superAdmin->setUsername($username);
        $superAdmin->setPassword('not-used');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($superAdmin);
        $entityManager->flush();

        return $username;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        return $data;
    }
}
