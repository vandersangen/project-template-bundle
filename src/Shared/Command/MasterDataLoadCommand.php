<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\Command;

use VanDerSangen\ProjectTemplateBundle\Shared\Service\MasterDataLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bundle:master-data:load',
    description: 'Load master data from configuration files into the database',
)]
class MasterDataLoadCommand extends Command
{
    public function __construct(
        private readonly MasterDataLoader $masterDataLoader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'purge',
                null,
                InputOption::VALUE_NONE,
                'Purge all data before loading fixtures (WARNING: Deletes all data!)'
            )
            ->addOption(
                'append',
                null,
                InputOption::VALUE_NONE,
                'Append data instead of purging (default behavior)'
            )
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command loads master data from module configuration files.

By default, it appends data without deleting existing records:
  <info>php %command.full_name%</info>

To purge all data before loading (WARNING: Deletes everything!):
  <info>php %command.full_name% --purge</info>

The command scans all modules for master_data directories and loads:
- Default configuration (src/*/master_data/default.php) - always loaded
- Environment-specific configuration (src/*/master_data/{env}.php) - if exists

Current environment: <comment>%kernel.environment%</comment>
HELP
            );
    }

    /**
     * @SuppressWarnings(PHPMD.ShortVariable)
     * @SuppressWarnings(PHPMD.LongVariable)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Master Data Loader');

        // Display environment information
        $environment = $this->masterDataLoader->getEnvironment();
        $io->section('Configuration');
        $io->text([
            sprintf('Environment: <info>%s</info>', $environment),
            sprintf('Source directory: <info>%s</info>', $this->masterDataLoader->getSrcDir()),
        ]);

        // Get modules with master_data directories
        $modulesWithMasterData = $this->masterDataLoader->getModulesWithMasterData();
        if (!empty($modulesWithMasterData)) {
            $io->text(sprintf('Modules with master_data: <info>%s</info>', implode(', ', $modulesWithMasterData)));
        }

        // Load and display configuration
        $config = $this->masterDataLoader->loadConfiguration();
        $configuredEntities = $this->masterDataLoader->getConfiguredModules();

        if (empty($configuredEntities)) {
            $io->warning('No master data configuration found!');
            $io->note('Create configuration files in src/*/master_data/default.php');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Configured entities: <info>%s</info>', implode(', ', $configuredEntities)));

        // Count total items
        $totalItems = 0;
        foreach ($configuredEntities as $entity) {
            $items = $this->masterDataLoader->getModuleConfiguration($entity);
            $totalItems += count($items);
            $io->text(sprintf('  - %s: <comment>%d items</comment>', $entity, count($items)));
        }

        $io->newLine();

        // Confirm if purging
        $purge = $input->getOption('purge');
        if ($purge) {
            $io->warning('PURGE MODE: All existing data will be deleted!');
            if ($input->isInteractive() && !$io->confirm('Are you sure you want to continue?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Load fixtures using doctrine:fixtures:load command
        $io->section('Loading Master Data');

        try {
            $command = $this->getApplication()->find('doctrine:fixtures:load');

            $arguments = [
                '--append' => !$purge,
            ];

            $greetInput = new ArrayInput($arguments);
            $greetInput->setInteractive(false);

            $returnCode = $command->run($greetInput, $output);

            if ($returnCode === Command::SUCCESS) {
                $io->success([
                    'Master data loaded successfully!',
                    sprintf('Loaded %d items from %d entity types', $totalItems, count($configuredEntities)),
                ]);
            } else {
                $io->error('Failed to load master data!');
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Failed to load master data!',
                $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
