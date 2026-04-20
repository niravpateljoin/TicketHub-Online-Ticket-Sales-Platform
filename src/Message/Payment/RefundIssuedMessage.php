<?php

namespace App\Message\Payment;

/**
 * Dispatched by EventCancellationService for each refunded booking.
 * Consumed by RefundIssuedMessageHandler (payment_queue).
 */
final class RefundIssuedMessage
{
    public function __construct(
        public readonly int $userId,
        public readonly int $amount,
        public readonly string $reason,
        public readonly int $bookingId,
    ) {}
}
