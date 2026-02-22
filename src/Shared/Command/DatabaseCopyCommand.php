<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\Command;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'bundle:database:copy',
    description: 'Copy a database to the current branch database',
)]
class DatabaseCopyCommand extends Command
{
    private const string DB_PREFIX = 'app_db_';

    private string $databaseHost;
    private string $databaseUser;
    private string $databasePassword;
    private ?Connection $systemConnection = null;

    public function __construct(
        private readonly string $environment,
        private readonly string $databaseUrl,
    ) {
        parent::__construct();

        // Extract connection parameters from DATABASE_URL
        $this->parseDatabaseUrl($databaseUrl);
    }

    private function parseDatabaseUrl(string $url): void
    {
        // Parse the DATABASE_URL to extract credentials
        // Format: mysql://user:password@host:port/dbname?options
        $parsed = parse_url($url);

        $this->databaseHost = $parsed['host'] ?? 'db';
        $this->databaseUser = $parsed['user'] ?? 'user';
        $this->databasePassword = $parsed['pass'] ?? 'password';
    }

    /**
     * Get a connection to the MySQL server without selecting a specific database.
     * This allows us to query for existing databases even if the branch-specific database doesn't exist yet.
     */
    private function getSystemConnection(): Connection
    {
        if ($this->systemConnection === null) {
            $connectionParams = [
                'driver' => 'pdo_mysql',
                'host' => $this->databaseHost,
                'user' => $this->databaseUser,
                'password' => $this->databasePassword,
                'charset' => 'utf8mb4',
            ];

            $this->systemConnection = DriverManager::getConnection($connectionParams);
        }

        return $this->systemConnection;
    }

    /**
     * @SuppressWarnings(PHPMD.ShortVariable)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Prevent execution in production environment. May only be run in 'dev' env's
        if ($this->environment !== 'dev') {
            $io->error([
                'This command cannot be executed in production environment!',
                'Database copying is only allowed in development environments.',
            ]);
            return Command::FAILURE;
        }

        $io->title('Git Branch Database Copy Tool');

        // Get current branch
        $currentBranch = $this->getCurrentBranch();
        $targetDb = self::DB_PREFIX . $this->sanitizeBranchName($currentBranch);

        $io->section('Current Configuration');
        $io->text([
            sprintf('Current Git branch: <info>%s</info>', $currentBranch),
            sprintf('Target database: <info>%s</info>', $targetDb),
        ]);

        // Check if target database already exists
        if ($this->databaseExists($targetDb)) {
            $io->warning(sprintf('Database "%s" already exists!', $targetDb));

            $question = new ConfirmationQuestion(
                'Do you want to overwrite it? (yes/no) ',
                false
            );

            if ($io->askQuestion($question) === false) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Get all available databases
        $io->section('Scanning for existing databases...');
        $databases = $this->getAllDatabases();

        if (empty($databases)) {
            $io->error(sprintf('No databases found with prefix "%s"', self::DB_PREFIX));
            $io->note('Please create a database first or check your database connection.');
            return Command::FAILURE;
        }

        // Display available databases
        $choices = [];
        foreach ($databases as $db) {
            if ($db === $targetDb) {
                $choices[] = sprintf('%s (current branch)', $db);
            } else {
                $choices[] = $db;
            }
        }

        $question = new ChoiceQuestion(
            'Select source database to copy from:',
            $choices
        );
        $question->setErrorMessage('Invalid selection.');

        $selectedChoice = $io->askQuestion($question);

        // Extract the actual database name (remove the "(current branch)" suffix if present)
        $sourceDb = preg_replace('/ \(current branch\)$/', '', (string) $selectedChoice);

        $io->newLine();
        $io->text([
            sprintf('Source database: <info>%s</info>', $sourceDb),
            sprintf('Target database: <info>%s</info>', $targetDb),
        ]);
        $io->newLine();

        // Confirm operation
        $question = new ConfirmationQuestion(
            'Proceed with copy? (yes/no) ',
            false
        );

        if (!$io->askQuestion($question)) {
            $io->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Perform the copy
        $io->newLine();
        if ($this->copyDatabase($sourceDb, $targetDb, $io)) {
            $io->success([
                'Database copy completed successfully!',
                sprintf('You can now use the database: %s', $targetDb),
            ]);
            return Command::SUCCESS;
        }

        $io->error('Database copy failed!');
        return Command::FAILURE;
    }

    /**
     * @SuppressWarnings(PHPMD.ErrorControlOperator)
     */
    private function getCurrentBranch(): string
    {
        $branch = @shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null');

        if ($branch === null || $branch === false || trim($branch) === '') {
            return 'main';
        }

        return trim($branch);
    }

    private function sanitizeBranchName(string $branch): string
    {
        // Replace special characters with underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $branch);

        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', (string) $sanitized);

        // Remove leading/trailing underscores
        $sanitized = trim((string) $sanitized, '_');

        // Convert to lowercase
        return strtolower($sanitized);
    }

    private function getAllDatabases(): array
    {
        try {
            $connection = $this->getSystemConnection();
            $sql = sprintf("SHOW DATABASES LIKE '%s%%'", self::DB_PREFIX);
            $result = $connection->fetchFirstColumn($sql);

            return $result ?: [];
        } catch (\Exception) {
            return [];
        }
    }

    private function databaseExists(string $dbName): bool
    {
        try {
            $connection = $this->getSystemConnection();
            $sql = sprintf("SHOW DATABASES LIKE '%s'", $dbName);
            $result = $connection->fetchOne($sql);

            return $result !== false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    private function copyDatabase(string $sourceDb, string $targetDb, SymfonyStyle $io): bool
    {
        try {
            $io->text(sprintf('<comment>Copying database: %s -> %s</comment>', $sourceDb, $targetDb));

            $connection = $this->getSystemConnection();

            // Drop and recreate target database to ensure clean state
            $io->text('<info>Recreating target database...</info>');
            $connection->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', $targetDb));
            $connection->executeStatement(
                sprintf(
                    'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    $targetDb
                )
            );

            // Get all tables from source database
            $io->text('<info>Copying schema and data...</info>');
            $tables = $connection->fetchFirstColumn(
                sprintf('SHOW TABLES FROM `%s`', $sourceDb)
            );

            if (empty($tables)) {
                $io->warning('Source database is empty. Created empty target database.');
                return true;
            }

            $progressBar = $io->createProgressBar(count($tables));
            $progressBar->start();

            foreach ($tables as $table) {
                // Get CREATE TABLE statement
                $createTableResult = $connection->fetchAssociative(
                    sprintf('SHOW CREATE TABLE `%s`.`%s`', $sourceDb, $table)
                );
                $createTableSql = $createTableResult['Create Table'];

                // Create table in target database
                $connection->executeStatement(sprintf('USE `%s`', $targetDb));
                $connection->executeStatement($createTableSql);

                // Copy data
                $connection->executeStatement(
                    sprintf(
                        'INSERT INTO `%s`.`%s` SELECT * FROM `%s`.`%s`',
                        $targetDb,
                        $table,
                        $sourceDb,
                        $table
                    )
                );

                $progressBar->advance();
            }

            $progressBar->finish();
            $io->newLine(2);

            return true;
        } catch (\Exception $e) {
            $io->error(sprintf('Error: %s', $e->getMessage()));
            return false;
        }
    }
}
