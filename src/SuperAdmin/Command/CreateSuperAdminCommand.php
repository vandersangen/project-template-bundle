<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\SuperAdmin\Command;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Repository\SuperAdminUserRepository;

#[AsCommand(
    name: 'bundle:super-admin:create',
    description: 'Maak een nieuwe super-admin gebruiker aan',
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SuperAdminUserRepository $repository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Super-Admin aanmaken');

        $username = $io->ask('Gebruikersnaam', null, function (?string $value): string {
            if (empty($value)) {
                throw new RuntimeException('Gebruikersnaam mag niet leeg zijn.');
            }
            return $value;
        });

        if ($this->repository->findByUsername($username) !== null) {
            $io->error(sprintf('Er bestaat al een super-admin met gebruikersnaam "%s".', $username));
            return Command::FAILURE;
        }

        $plainPassword = $io->askHidden('Wachtwoord', function (?string $value): string {
            if (empty($value)) {
                throw new RuntimeException('Wachtwoord mag niet leeg zijn.');
            }
            if (strlen($value) < 12) {
                throw new RuntimeException('Wachtwoord moet minimaal 12 tekens bevatten.');
            }
            return $value;
        });

        $confirmation = $io->askHidden('Bevestig wachtwoord', function (?string $value): string {
            if (empty($value)) {
                throw new RuntimeException('Bevestiging mag niet leeg zijn.');
            }
            return $value;
        });

        if ($plainPassword !== $confirmation) {
            $io->error('Wachtwoorden komen niet overeen.');
            return Command::FAILURE;
        }

        $user = new SuperAdminUser();
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Super-admin "%s" is succesvol aangemaakt.', $username));

        return Command::SUCCESS;
    }
}
