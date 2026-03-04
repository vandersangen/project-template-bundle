<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Command;

use DateTimeImmutable;
use VanDerSangen\ProjectTemplateBundle\Cron\Repository\CronRepository;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronScheduleResolver;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\RunCronMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\Service\QueueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'bundle:cron:schedule',
    description: 'Find due crons and dispatch them to the async queue',
)]
class ScheduleCronCommand extends Command
{
    public function __construct(
        private readonly CronRepository $cronRepository,
        private readonly CronScheduleResolver $cronScheduleResolver,
        private readonly QueueService $queueService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new DateTimeImmutable();
        $due = $this->cronRepository->findDue($now);

        foreach ($due as $cron) {
            $this->queueService->dispatch(new RunCronMessage($cron->getId()));
            $cron->setNextRunAt($this->cronScheduleResolver->getNextRunAt($cron, $now));
            $this->cronRepository->save($cron, true);
        }

        $io->success(sprintf('Dispatched %d cron(s) to the queue.', count($due)));

        return self::SUCCESS;
    }
}
