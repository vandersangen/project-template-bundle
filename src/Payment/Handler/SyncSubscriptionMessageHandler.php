<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Handler;

use VanDerSangen\ProjectTemplateBundle\Payment\Message\SyncSubscriptionMessage;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\SubscriptionRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Queue\Handler\AsyncMessageHandlerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
/**
 * @deprecated Handler for the unused SyncSubscriptionMessage. See that class for details.
 */
class SyncSubscriptionMessageHandler implements AsyncMessageHandlerInterface
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function __invoke(SyncSubscriptionMessage $message): void
    {
        $subscription = $this->subscriptionRepository->find($message->getSubscriptionId());
        if ($subscription === null) {
            throw new RuntimeException(sprintf('Subscription %d not found.', $message->getSubscriptionId()));
        }

        $this->paymentService->syncSubscription($subscription, $message->isForceSync());
    }
}
