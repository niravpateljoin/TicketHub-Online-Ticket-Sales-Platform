<?php

namespace App\MessageHandler\Payment;

use App\Message\Payment\RefundIssuedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consumes RefundIssuedMessage from payment_queue.
 *
 * Current responsibility: audit-log the refund.
 * Future: could relay to an external payment gateway or update a separate payment ledger.
 *
 * The actual credit refund is already applied synchronously by EventCancellationService
 * inside its DB transaction — this handler handles post-processing only.
 */
#[AsMessageHandler]
final class RefundIssuedMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(RefundIssuedMessage $message): void
    {
        $this->logger->info(
            'RefundIssuedMessage: {amount} credits refunded to user #{userId} for booking #{bookingId}. Reason: {reason}',
            [
                'amount'    => $message->amount,
                'userId'    => $message->userId,
                'bookingId' => $message->bookingId,
                'reason'    => $message->reason,
            ]
        );

        // Future: $this->paymentGateway->issueRefund($message->userId, $message->amount);
    }
}
