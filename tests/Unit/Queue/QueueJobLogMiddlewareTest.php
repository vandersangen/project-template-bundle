<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use VanDerSangen\ProjectTemplateBundle\Queue\Entity\QueueJobLog;
use VanDerSangen\ProjectTemplateBundle\Queue\Enum\QueueJobLogStatus;
use VanDerSangen\ProjectTemplateBundle\Queue\Message\SendMailMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\Middleware\QueueJobLogMiddleware;
use VanDerSangen\ProjectTemplateBundle\Queue\Repository\QueueJobLogRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class QueueJobLogMiddlewareTest extends TestCase
{
    private QueueJobLogRepository $repository;
    private QueueJobLogMiddleware $middleware;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(QueueJobLogRepository::class);
        $this->middleware = new QueueJobLogMiddleware($this->repository);
    }

    public function testSuccessfulHandleSetsCompletedStatus(): void
    {
        $message = new SendMailMessage(42);
        $envelope = new Envelope($message);
        $savedLogs = [];
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLogs) {
                $savedLogs[] = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->expects($this->once())
            ->method('handle')
            ->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->expects($this->once())
            ->method('next')
            ->willReturn($nextMiddleware);
        $result = $this->middleware->handle($envelope, $stack);
        $this->assertSame($envelope, $result);
        $this->assertCount(2, $savedLogs);
        $this->assertEquals(QueueJobLogStatus::STARTED, $savedLogs[0]->getStatus());
        $this->assertEquals(QueueJobLogStatus::COMPLETED, $savedLogs[1]->getStatus());
        $this->assertNotNull($savedLogs[1]->getCompletedAt());
    }

    public function testFailedHandleSetsFailedStatusAndRethrows(): void
    {
        $message = new SendMailMessage(99);
        $envelope = new Envelope($message);
        $savedLogs = [];
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLogs) {
                $savedLogs[] = clone $log;
            });
        $exception = new \RuntimeException('Mail server connection refused');
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);
        $stack = $this->createMock(StackInterface::class);
        $stack->expects($this->once())
            ->method('next')
            ->willReturn($nextMiddleware);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mail server connection refused');
        try {
            $this->middleware->handle($envelope, $stack);
        } finally {
            $this->assertCount(2, $savedLogs);
            $this->assertEquals(QueueJobLogStatus::STARTED, $savedLogs[0]->getStatus());
            $this->assertEquals(QueueJobLogStatus::FAILED, $savedLogs[1]->getStatus());
            $this->assertStringContainsString('Mail server connection refused', $savedLogs[1]->getStderr());
            $this->assertNotNull($savedLogs[1]->getCompletedAt());
        }
    }

    public function testMessageClassIsStored(): void
    {
        $message = new SendMailMessage(1);
        $envelope = new Envelope($message);
        $savedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLog) {
                $savedLog = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertEquals(SendMailMessage::class, $savedLog->getMessageClass());
    }

    public function testMessageDataIsExtracted(): void
    {
        $message = new SendMailMessage(42);
        $envelope = new Envelope($message);
        $savedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLog) {
                $savedLog = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertEquals(['mailId' => 42], $savedLog->getMessageData());
    }

    public function testStdoutIsCaptured(): void
    {
        $message = new SendMailMessage(10);
        $envelope = new Envelope($message);
        $savedLogs = [];
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLogs) {
                $savedLogs[] = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')
            ->willReturnCallback(function () use ($envelope) {
                echo 'Processing message...';
                return $envelope;
            });
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertEquals('Processing message...', $savedLogs[1]->getStdout());
    }

    public function testEmptyStdoutIsStoredAsNull(): void
    {
        $message = new SendMailMessage(10);
        $envelope = new Envelope($message);
        $savedLogs = [];
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLogs) {
                $savedLogs[] = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertNull($savedLogs[1]->getStdout());
    }

    public function testStderrContainsExceptionTrace(): void
    {
        $message = new SendMailMessage(5);
        $envelope = new Envelope($message);
        $savedLogs = [];
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLogs) {
                $savedLogs[] = clone $log;
            });
        $exception = new \RuntimeException('SMTP timeout');
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willThrowException($exception);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        try {
            $this->middleware->handle($envelope, $stack);
        } catch (\RuntimeException) {
            // expected
        }
        $this->assertStringContainsString('SMTP timeout', $savedLogs[1]->getStderr());
        $this->assertStringContainsString('#0', $savedLogs[1]->getStderr());
    }

    public function testStartedAtIsSetBeforeHandling(): void
    {
        $message = new SendMailMessage(1);
        $envelope = new Envelope($message);
        $firstSavedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$firstSavedLog) {
                if ($firstSavedLog === null) {
                    $firstSavedLog = clone $log;
                }
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $before = new \DateTimeImmutable();
        $this->middleware->handle($envelope, $stack);
        $this->assertNotNull($firstSavedLog->getStartedAt());
        $this->assertGreaterThanOrEqual($before, $firstSavedLog->getStartedAt());
    }

    public function testStdoutCapturedOnFailure(): void
    {
        $message = new SendMailMessage(7);
        $envelope = new Envelope($message);
        $savedLogs = [];
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLogs) {
                $savedLogs[] = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')
            ->willReturnCallback(function () {
                echo 'Partial output before crash';
                throw new \RuntimeException('Crash');
            });
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        try {
            $this->middleware->handle($envelope, $stack);
        } catch (\RuntimeException) {
            // expected
        }
        $this->assertEquals('Partial output before crash', $savedLogs[1]->getStdout());
        $this->assertStringContainsString('Crash', $savedLogs[1]->getStderr());
    }

    public function testMessageWithUninitializedPropertyIsStoredAsUninitialized(): void
    {
        $message = new class () {
            public \stdClass $uninitialized;
        };
        $envelope = new Envelope($message);
        $savedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLog) {
                $savedLog = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertSame(['uninitialized' => '[uninitialized]'], $savedLog->getMessageData());
    }

    public function testMessageWithObjectPropertyWithoutToStringIsStoredAsTypeString(): void
    {
        $message = new readonly class (new \stdClass()) {
            public function __construct(
                public \stdClass $obj,
            ) {
            }
        };
        $envelope = new Envelope($message);
        $savedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLog) {
                $savedLog = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertSame(['obj' => '[object stdClass]'], $savedLog->getMessageData());
    }

    public function testMessageWithObjectPropertyWithToStringIsStoredAsString(): void
    {
        $withToString = new class () {
            public function __toString(): string
            {
                return 'custom-string-value';
            }
        };
        $message = new readonly class ($withToString) {
            public function __construct(
                public object $at,
            ) {
            }
        };
        $envelope = new Envelope($message);
        $savedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLog) {
                $savedLog = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertSame('custom-string-value', $savedLog->getMessageData()['at']);
    }

    public function testMessageWithArrayContainingObjectSanitizesNestedObject(): void
    {
        $message = new readonly class ([new \stdClass()]) {
            public function __construct(
                public array $items,
            ) {
            }
        };
        $envelope = new Envelope($message);
        $savedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLog) {
                $savedLog = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $this->assertSame(['items' => [0 => '[object stdClass]']], $savedLog->getMessageData());
    }

    public function testMessageWithObjectWhoseToStringThrowsIsStoredAsTypeString(): void
    {
        $message = new readonly class (new class () {
            public function __toString(): string
            {
                throw new \RuntimeException('toString fails');
            }
        }) {
            public function __construct(
                public object $bad,
            ) {
            }
        };
        $envelope = new Envelope($message);
        $savedLog = null;
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (QueueJobLog $log) use (&$savedLog) {
                $savedLog = clone $log;
            });
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);
        $this->middleware->handle($envelope, $stack);
        $data = $savedLog->getMessageData();
        $this->assertArrayHasKey('bad', $data);
        $this->assertIsString($data['bad']);
        $this->assertStringStartsWith('[object ', $data['bad']);
    }
}
