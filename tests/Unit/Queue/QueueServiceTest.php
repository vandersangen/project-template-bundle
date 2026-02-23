<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Queue;

use VanDerSangen\ProjectTemplateBundle\Queue\Message\SendMailMessage;
use VanDerSangen\ProjectTemplateBundle\Queue\Service\QueueService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class QueueServiceTest extends TestCase
{
    private MessageBusInterface $messageBus;
    private QueueService $queueService;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->queueService = new QueueService($this->messageBus);
    }

    public function testDispatchSendsMessageToBus(): void
    {
        $message = new SendMailMessage(1);
        $envelope = new Envelope($message);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($message, [])
            ->willReturn($envelope);
        $result = $this->queueService->dispatch($message);
        $this->assertSame($envelope, $result);
    }

    public function testDispatchWithCustomStamps(): void
    {
        $message = new SendMailMessage(1);
        $stamp = new TransportNamesStamp(['async']);
        $envelope = new Envelope($message);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($message, [$stamp])
            ->willReturn($envelope);
        $result = $this->queueService->dispatch($message, [$stamp]);
        $this->assertSame($envelope, $result);
    }

    public function testDispatchToTransport(): void
    {
        $message = new SendMailMessage(5);
        $envelope = new Envelope($message);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $message,
                $this->callback(fn(array $stamps) => count($stamps) === 1
                    && $stamps[0] instanceof TransportNamesStamp)
            )
            ->willReturn($envelope);
        $result = $this->queueService->dispatchToTransport($message, 'async');
        $this->assertSame($envelope, $result);
    }

    public function testDispatchReturnsEnvelope(): void
    {
        $message = new SendMailMessage(10);
        $envelope = new Envelope($message);
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);
        $result = $this->queueService->dispatch($message);
        $this->assertInstanceOf(Envelope::class, $result);
        $this->assertSame($message, $result->getMessage());
    }
}
