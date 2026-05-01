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

        \Sentry\addBreadcrumb(new \Sentry\Breadcrumb(
            \Sentry\Breadcrumb::LEVEL_INFO,
            \Sentry\Breadcrumb::TYPE_DEFAULT,
            'queue',
            sprintf('Processing: %s', $message::class),
            $jobLog->getMessageData() ?? [],
        ));

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
            try {
                $value = $property->getValue($message);
            } catch (\Throwable) {
                $data[$property->getName()] = '[uninitialized]';
                continue;
            }

            if (is_scalar($value) || is_null($value)) {
                $data[$property->getName()] = $value;
                continue;
            }

            if (is_array($value)) {
                $data[$property->getName()] = $this->sanitizeArray($value);
                continue;
            }

            if (is_object($value)) {
                $data[$property->getName()] = $this->objectToLogString($value);
            }
        }

        return $data;
    }

    private function sanitizeArray(array $value): array
    {
        $out = [];
        foreach ($value as $k => $v) {
            if (is_scalar($v) || is_null($v)) {
                $out[$k] = $v;
            } elseif (is_array($v)) {
                $out[$k] = $this->sanitizeArray($v);
            } elseif (is_object($v)) {
                $out[$k] = $this->objectToLogString($v);
            }
        }
        return $out;
    }

    private function objectToLogString(object $value): string
    {
        if (method_exists($value, '__toString')) {
            try {
                return (string) $value;
            } catch (\Throwable) {
                // fallback if __toString throws
            }
        }
        return '[object ' . get_debug_type($value) . ']';
    }
}
