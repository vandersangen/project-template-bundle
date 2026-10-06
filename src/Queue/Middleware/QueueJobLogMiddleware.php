<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Middleware;

use VanDerSangen\ProjectTemplateBundle\Queue\Entity\QueueJobLog;
use VanDerSangen\ProjectTemplateBundle\Queue\Enum\QueueJobLogStatus;
use VanDerSangen\ProjectTemplateBundle\Queue\QueueJobLogContext;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;
use DateTimeImmutable;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class QueueJobLogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly QueueJobLogRepository $logRepository,
        private readonly QueueJobLogContext $context = new QueueJobLogContext(),
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
        $this->context->enter($jobLog);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);

            $jobLog->setStdout($this->joinOutput($jobLog->getStdout(), ob_get_clean()));
            if ($this->wasOnlyQueued($envelope)) {
                $jobLog->setStatus(QueueJobLogStatus::QUEUED);
            } else {
                $jobLog->setStatus(QueueJobLogStatus::COMPLETED);
                $jobLog->setCompletedAt(new DateTimeImmutable());
            }

            $this->logRepository->save($jobLog, true);

            return $envelope;
        } catch (\Throwable $exception) {
            $jobLog->setStdout($this->joinOutput($jobLog->getStdout(), ob_get_clean()));
            $jobLog->setStderr($exception->getMessage() . "\n" . $exception->getTraceAsString());
            $jobLog->setStatus(QueueJobLogStatus::FAILED);
            $jobLog->setCompletedAt(new DateTimeImmutable());

            $this->logRepository->save($jobLog, true);

            throw $exception;
        } finally {
            $this->context->leave();
        }
    }

    /**
     * Sent to a transport and not received from one: nothing was handled here, a worker will log its own line.
     *
     * @param Envelope $envelope The envelope as the rest of the stack returned it.
     *
     * @return bool
     */
    private function wasOnlyQueued(Envelope $envelope): bool
    {
        return $envelope->last(SentStamp::class) !== null && $envelope->last(ReceivedStamp::class) === null;
    }

    /**
     * What a handler wrote to its own line (see QueueJobLogContext), followed by what it echoed.
     *
     * @param string|null  $written What the handler put on the line itself.
     * @param string|false $echoed  The output buffer.
     *
     * @return string|null
     */
    private function joinOutput(?string $written, string|false $echoed): ?string
    {
        $output = ($written ?? '') . ($echoed ?: '');

        return $output === '' ? null : $output;
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
