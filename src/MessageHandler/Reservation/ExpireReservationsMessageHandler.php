<?php

namespace App\MessageHandler\Reservation;

use App\Entity\SeatReservation;
use App\Message\Reservation\ExpireReservationsMessage;
use App\Repository\SeatReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consumes ExpireReservationsMessage from reservation_queue.
 * Expires all pending SeatReservations whose expiresAt has passed.
 *
 * This replaces (or supplements) the cron-based ExpireSeatReservationsCommand:
 * a cron job dispatches ExpireReservationsMessage every minute, and this handler
 * does the actual work in the worker process.
 */
#[AsMessageHandler]
final class ExpireReservationsMessageHandler
{
    public function __construct(
        private readonly SeatReservationRepository $seatReservationRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ExpireReservationsMessage $message): void
    {
        $expired = $this->seatReservationRepository->findExpiredPending();

        if ($expired === []) {
            return;
        }

        foreach ($expired as $reservation) {
            $reservation->setStatus(SeatReservation::STATUS_EXPIRED);
        }

        $this->em->flush();

        $this->logger->info('ExpireReservationsMessage: expired {count} reservation(s).', [
            'count' => count($expired),
        ]);
    }
}
