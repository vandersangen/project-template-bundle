<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Queue\EventSubscriber;

use Sentry\State\HubInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class SentryMessengerSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly HubInterface $hub)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'onMessageFailed'];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $this->hub->captureException($event->getThrowable());
    }
}
