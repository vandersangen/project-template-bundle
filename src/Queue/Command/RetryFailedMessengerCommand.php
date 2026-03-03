<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'bundle:queue:retry-failed',
    description: 'Retry failed messenger jobs from the "failed" transport',
)]
class RetryFailedMessengerCommand extends Command
{
    private const string FAILED_TRANSPORT = 'failed';

    protected function configure(): void
    {
        $this
            ->addArgument(
                'id',
                InputArgument::IS_ARRAY,
                'Specific message id(s) to retry (from messenger:failed:show failed)'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Retry all without confirmation'
            )
            // @codingStandardsIgnoreStart
            ->setHelp(
                'Failed jobs are stored in the "failed" transport. This command runs '
                . 'messenger:failed:retry with transport=failed. Use --force to retry all without confirmation, '
                . 'or run without options for interactive mode. For cron, use: bundle:queue:retry-failed --force'
            );
            // @codingStandardsIgnoreEnd
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            return self::FAILURE;
        }

        $retryCommand = $application->find('messenger:failed:retry');
        $retryInput = new ArrayInput([
            'id' => $input->getArgument('id'),
            '--transport' => self::FAILED_TRANSPORT,
            '--force' => $input->getOption('force'),
        ]);

        $retryInput->setInteractive($input->isInteractive());

        $code = $retryCommand->run($retryInput, $output);

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}
