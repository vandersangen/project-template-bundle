<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SuperAdminUserFixture extends Fixture
{
    public const TEST_USERNAME = 'superadmin';
    public const TEST_PASSWORD = 'test';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new SuperAdminUser();
        $user->setUsername(self::TEST_USERNAME);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::TEST_PASSWORD));
        $manager->persist($user);
        $manager->flush();
    }
}
