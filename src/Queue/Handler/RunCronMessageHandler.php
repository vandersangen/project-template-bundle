<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Handler;

use DateTimeImmutable;
use RuntimeException;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Repository\CronRepository;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronScheduleResolver;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\RunCronMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\ProcessRunnerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
class RunCronMessageHandler implements AsyncMessageHandlerInterface
{
    public function __construct(
        private readonly CronRepository $cronRepository,
        private readonly CronScheduleResolver $cronScheduleResolver,
        private readonly KernelInterface $kernel,
        private readonly ProcessRunnerInterface $processRunner,
    ) {
    }

    public function __invoke(RunCronMessage $message): void
    {
        $cron = $this->cronRepository->find($message->getCronId());
        if (!$cron instanceof Cron) {
            throw new RuntimeException(sprintf('Cron with ID %d not found.', $message->getCronId()));
        }

        $commandLine = [
            \PHP_BINARY,
            $this->kernel->getProjectDir() . '/bin/console',
            $cron->getCommand(),
        ];
        $args = $cron->getCommandArguments();
        if (is_array($args)) {
            foreach ($args as $key => $value) {
                if (is_int($key)) {
                    $commandLine[] = (string) $value;
                    continue;
                }

                $commandLine[] = '--' . $key . '=' . $value;
            }
        }
        $process = new Process($commandLine);
        $process->setTimeout(null);
        if (!$this->processRunner->run($process)) {
            try {
                $errorOutput = $process->getErrorOutput();
            } catch (\Exception) {
                $errorOutput = '(output not available)';
            }
            throw new RuntimeException('Cron command failed: ' . $errorOutput);
        }

        $now = new DateTimeImmutable();
        $cron->setLastRunAt($now);
        $cron->setNextRunAt($this->cronScheduleResolver->getNextRunAt($cron, $now));
        $this->cronRepository->save($cron, true);
    }
}
