<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Handler;

use VanDerSangen\ProjectTemplateBundle\Payment\Enum\PaymentProvider;
use VanDerSangen\ProjectTemplateBundle\Payment\Enum\SubscriptionInterval;
use VanDerSangen\ProjectTemplateBundle\Payment\Message\CreateSubscriptionMessage;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Queue\Handler\AsyncMessageHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
/**
 * @deprecated Handler for the unused CreateSubscriptionMessage. See that class for details.
 */
class CreateSubscriptionMessageHandler implements AsyncMessageHandlerInterface
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function __invoke(CreateSubscriptionMessage $message): void
    {
        $this->paymentService->createSubscription(
            tenantId: $message->getTenantId(),
            userId: $message->getUserId(),
            provider: PaymentProvider::from($message->getProvider()),
            amountCents: $message->getAmountCents(),
            interval: SubscriptionInterval::from($message->getInterval()),
            returnUrl: $message->getReturnUrl(),
            currency: $message->getCurrency(),
            description: $message->getDescription(),
            cancelUrl: $message->getCancelUrl(),
        );
    }
}
