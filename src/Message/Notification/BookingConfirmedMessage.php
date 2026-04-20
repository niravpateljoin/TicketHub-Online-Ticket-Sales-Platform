<?php

namespace App\Message\Notification;

/**
 * Dispatched by CheckoutService after a booking is confirmed.
 * Consumed by BookingConfirmedMessageHandler (notification_queue).
 */
final class BookingConfirmedMessage
{
    public function __construct(
        public readonly int $bookingId,
        public readonly string $userEmail,
    ) {}
}
