<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\SuperAdmin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Command\CreateSuperAdminCommand;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Repository\SuperAdminUserRepository;

class CreateSuperAdminCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SuperAdminUserRepository $repository;
    private UserPasswordHasherInterface $passwordHasher;
    private CreateSuperAdminCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(SuperAdminUserRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->command = new CreateSuperAdminCommand(
            $this->entityManager,
            $this->repository,
            $this->passwordHasher,
        );
    }

    public function testSuccessfulCreation(): void
    {
        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with('newadmin')
            ->willReturn(null);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(SuperAdminUser::class), 'StrongPass123!')
            ->willReturn('hashed_password');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(SuperAdminUser::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $tester = new CommandTester($this->command);
        $tester->setInputs(['newadmin', 'StrongPass123!', 'StrongPass123!']);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('newadmin', $tester->getDisplay());
    }

    public function testFailsWhenUsernameAlreadyExists(): void
    {
        $existingUser = new SuperAdminUser();
        $existingUser->setUsername('existing');

        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->with('existing')
            ->willReturn($existingUser);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $tester = new CommandTester($this->command);
        $tester->setInputs(['existing']);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('existing', $tester->getDisplay());
    }

    public function testFailsWhenPasswordsDoNotMatch(): void
    {
        $this->repository->expects($this->once())
            ->method('findByUsername')
            ->willReturn(null);

        $this->passwordHasher->expects($this->never())->method('hashPassword');
        $this->entityManager->expects($this->never())->method('persist');

        $tester = new CommandTester($this->command);
        $tester->setInputs(['admin', 'StrongPass123!', 'DifferentPass456!']);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('niet overeen', $tester->getDisplay());
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('bundle:super-admin:create', $this->command->getName());
        $this->assertNotEmpty($this->command->getDescription());
    }
}
