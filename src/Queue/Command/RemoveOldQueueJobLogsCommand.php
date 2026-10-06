<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;

/**
 * Removes old queue_job_logs lines. Nothing runs it by itself: a project schedules it as a cron, or removes the lines
 * in its own clean-up through QueueJobLogRepository::removeStartedBefore().
 */
#[AsCommand(
    name: 'bundle:queue:remove-old-logs',
    description: 'Remove queue_job_logs lines older than the retention period',
)]
class RemoveOldQueueJobLogsCommand extends Command
{
    private const int DEFAULT_DAYS = 30;

    /**
     * @param QueueJobLogRepository $queueJobLogRepository The lines.
     */
    public function __construct(
        private readonly QueueJobLogRepository $queueJobLogRepository,
    ) {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Keep the lines of this many days',
            (string) self::DEFAULT_DAYS,
        );
    }

    /**
     * @param InputInterface  $input  The --days option.
     * @param OutputInterface $output Where the count goes.
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $output->writeln('<error>--days must be 1 or more.</error>');

            return self::INVALID;
        }

        $removed = $this->queueJobLogRepository->removeStartedBefore(new DateTimeImmutable(sprintf('-%d days', $days)));
        $output->writeln(sprintf('Removed %d queue_job_logs lines older than %d days.', $removed, $days));

        return self::SUCCESS;
    }
}
