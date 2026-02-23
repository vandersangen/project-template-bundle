<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Middleware;

use VanDerSangen\ProjectTemplateBundle\Queue\Entity\QueueJobLog;
use VanDerSangen\ProjectTemplateBundle\Queue\Enum\QueueJobLogStatus;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;
use DateTimeImmutable;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class QueueJobLogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly QueueJobLogRepository $logRepository,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        $jobLog = new QueueJobLog();
        $jobLog->setMessageClass($message::class);
        $jobLog->setMessageData($this->extractMessageData($message));
        $jobLog->setStatus(QueueJobLogStatus::STARTED);

        $this->logRepository->save($jobLog, true);

        ob_start();

        try {
            $envelope = $stack->next()->handle($envelope, $stack);

            $stdout = ob_get_clean();
            $jobLog->setStdout($stdout ?: null);
            $jobLog->setStatus(QueueJobLogStatus::COMPLETED);
            $jobLog->setCompletedAt(new DateTimeImmutable());

            $this->logRepository->save($jobLog, true);

            return $envelope;
        } catch (\Throwable $exception) {
            $stdout = ob_get_clean();
            $jobLog->setStdout($stdout ?: null);
            $jobLog->setStderr($exception->getMessage() . "\n" . $exception->getTraceAsString());
            $jobLog->setStatus(QueueJobLogStatus::FAILED);
            $jobLog->setCompletedAt(new DateTimeImmutable());

            $this->logRepository->save($jobLog, true);

            throw $exception;
        }
    }

    private function extractMessageData(object $message): array
    {
        $data = [];
        $reflection = new ReflectionClass($message);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($message);

            if (is_scalar($value) || is_null($value) || is_array($value)) {
                $data[$property->getName()] = $value;
                continue;
            }

            $data[$property->getName()] = (string) $value;
        }

        return $data;
    }
}
