<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\Service;

use VanDerSangen\ProjectTemplateBundle\Queue\Message\AsyncMessageInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class QueueService
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function dispatch(AsyncMessageInterface $message, array $stamps = []): Envelope
    {
        return $this->messageBus->dispatch($message, $stamps);
    }

    public function dispatchToTransport(AsyncMessageInterface $message, string $transport): Envelope
    {
        return $this->messageBus->dispatch($message, [
            new TransportNamesStamp([$transport]),
        ]);
    }
}
