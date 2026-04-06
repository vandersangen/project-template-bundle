<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Payment\Handler;

use VanDerSangen\ProjectTemplateBundle\Payment\Message\SyncPaymentMessage;
use VanDerSangen\ProjectTemplateBundle\Payment\Repository\PaymentRepository;
use VanDerSangen\ProjectTemplateBundle\Payment\Service\PaymentService;
use VanDerSangen\ProjectTemplateBundle\Queue\Handler\AsyncMessageHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
/**
 * @deprecated Handler for the unused SyncPaymentMessage. See that class for details.
 */
class SyncPaymentMessageHandler implements AsyncMessageHandlerInterface
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    public function __invoke(SyncPaymentMessage $message): void
    {
        $payment = $this->paymentRepository->find($message->getPaymentId());
        if ($payment === null) {
            throw new \RuntimeException(sprintf('Payment %d not found.', $message->getPaymentId()));
        }

        $this->paymentService->syncPayment($payment, $message->isForceSync());
    }
}
