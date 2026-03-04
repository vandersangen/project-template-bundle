<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\SuperAdmin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Repository\SuperAdminUserRepository;

class SuperAdminLoginTest extends WebTestCase
{
    private const string USERNAME = 'functional-superadmin';
    private const string PASSWORD = 'TestPassword1!';

    protected function setUp(): void
    {
        static::bootKernel();
        $container = static::getContainer();

        /** @var SuperAdminUserRepository $repo */
        $repo = $container->get(SuperAdminUserRepository::class);
        if ($repo->findByUsername(self::USERNAME) === null) {
            $em = $container->get('doctrine')->getManager();
            /** @var UserPasswordHasherInterface $hasher */
            $hasher = $container->get(UserPasswordHasherInterface::class);
            $user = new SuperAdminUser();
            $user->setUsername(self::USERNAME);
            $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
            $em->persist($user);
            $em->flush();
        }

        static::ensureKernelShutdown();
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/super-admin/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }

    public function testLoginRedirectsToSuperAdminOnSuccess(): void
    {
        $client = static::createClient();

        $crawler = $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/super-admin/login');
        $form = $crawler->selectButton('Inloggen')->form([
            '_username' => self::USERNAME,
            '_password' => self::PASSWORD,
        ]);
        $client->submit($form);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithWrongPasswordShowsError(): void
    {
        $client = static::createClient();

        $crawler = $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/super-admin/login');
        $form = $crawler->selectButton('Inloggen')->form([
            '_username' => self::USERNAME,
            '_password' => 'WrongPassword!',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/super-admin/login');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testLoginWithUnknownUserShowsError(): void
    {
        $client = static::createClient();

        $crawler = $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/super-admin/login');
        $form = $crawler->selectButton('Inloggen')->form([
            '_username' => 'nonexistent-user',
            '_password' => 'SomePassword1!',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/super-admin/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testProtectedRouteRedirectsUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/super-admin/crons');

        $this->assertResponseRedirects('/super-admin/login');
    }

    public function testLogoutRedirectsToLogin(): void
    {
        $client = static::createClient();

        $crawler = $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/super-admin/login');
        $form = $crawler->selectButton('Inloggen')->form([
            '_username' => self::USERNAME,
            '_password' => self::PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();

        $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/super-admin/logout');
        $this->assertResponseRedirects('/super-admin/login');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testUnauthenticatedPostToProtectedRouteRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request(\Symfony\Component\HttpFoundation\Request::METHOD_POST, '/super-admin/crons');

        $this->assertResponseRedirects('/super-admin/login');
    }
}
