<?php

namespace App\Message\Reservation;

/**
 * Trigger-only message — no payload.
 * Dispatched every minute by a cron job or Symfony Scheduler.
 * Consumed by ExpireReservationsMessageHandler (reservation_queue).
 */
final class ExpireReservationsMessage {}
