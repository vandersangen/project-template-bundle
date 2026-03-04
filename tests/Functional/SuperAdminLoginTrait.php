<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;

trait SuperAdminLoginTrait
{
    private function createSuperAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $username = 'test-superadmin-' . uniqid();
        $password = 'test';
        $user = new SuperAdminUser();
        $user->setUsername($username);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em->persist($user);
        $em->flush();

        $crawler = $client->request('GET', '/super-admin/login');
        $form = $crawler->selectButton('Inloggen')->form([
            '_username' => $username,
            '_password' => $password,
        ]);
        $client->submit($form);

        return $client;
    }
}
