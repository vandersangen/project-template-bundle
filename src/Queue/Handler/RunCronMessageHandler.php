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
use VanDerSangen\ProjectTemplateBundle\Queue\QueueJobLogContext;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

/**
 * Runs the cron's command. What it prints goes into the message's own queue_job_logs line while it runs, about once a
 * second, so a run can be followed live; the newest output is kept when it grows past MAX_OUTPUT_CHARACTERS.
 */
#[AsMessageHandler]
class RunCronMessageHandler implements AsyncMessageHandlerInterface
{
    /** The most output kept; beyond it the start is dropped, so the end (the result) always stays. */
    private const int MAX_OUTPUT_CHARACTERS = 200_000;

    public function __construct(
        private readonly CronRepository $cronRepository,
        private readonly CronScheduleResolver $cronScheduleResolver,
        private readonly KernelInterface $kernel,
        private readonly ProcessRunnerInterface $processRunner,
        private readonly QueueJobLogContext $queueJobLogContext,
        private readonly QueueJobLogRepository $queueJobLogRepository,
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
        if (!$this->processRunner->run($process, $this->writeOutputToTheLog())) {
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

    /**
     * @return callable(string): void
     */
    private function writeOutputToTheLog(): callable
    {
        $queueJobLog = $this->queueJobLogContext->current();

        return function (string $output) use ($queueJobLog): void {
            if ($queueJobLog === null || $queueJobLog->getId() === null) {
                return;
            }

            if (mb_strlen($output) > self::MAX_OUTPUT_CHARACTERS) {
                $output = "… (start left out)\n" . mb_substr($output, -self::MAX_OUTPUT_CHARACTERS);
            }

            $queueJobLog->setStdout($output);
            $this->queueJobLogRepository->saveOutputSoFar($queueJobLog->getId(), $output);
        };
    }
}
