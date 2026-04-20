<?php

namespace App\Message\Notification;

/**
 * Dispatched by EventCancellationService after all refunds are processed.
 * Consumed by EventCancelledMessageHandler (notification_queue).
 *
 * @param array<int,int> $refundMap  userId => creditsRefunded
 */
final class EventCancelledMessage
{
    public function __construct(
        public readonly int $eventId,
        public readonly string $eventName,
        public readonly array $refundMap,
    ) {}
}
