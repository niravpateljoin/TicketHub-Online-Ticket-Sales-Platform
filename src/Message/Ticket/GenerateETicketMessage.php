<?php

namespace App\Message\Ticket;

/**
 * Dispatched by CheckoutService after a booking is confirmed.
 * Consumed by GenerateETicketMessageHandler (ticket_queue).
 */
final class GenerateETicketMessage
{
    public function __construct(
        public readonly int $eTicketId,
        public readonly int $bookingId,
    ) {}
}
