<?php

namespace App\Service;

use App\Dto\CancellationResult;
use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\SeatReservation;
use App\Entity\Transaction;
use App\Message\Notification\EventCancelledMessage;
use App\Message\Payment\RefundIssuedMessage;
use App\Service\Cache\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EventCancellationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly CacheService $cache,
    ) {}

    public function cancel(Event $event, string $referencePrefix = 'Event cancelled'): CancellationResult
    {
        if ($event->getStatus() === Event::STATUS_CANCELLED) {
            return new CancellationResult(0, 0);
        }

        // Collect refund data inside the transaction; dispatch messages after commit
        // so workers only see fully-persisted state.
        $refundMap        = [];   // [userId => creditsRefunded]
        $refundedBookings = 0;

        $this->em->wrapInTransaction(function () use ($event, $referencePrefix, &$refundMap, &$refundedBookings): void {
            $event->setStatus(Event::STATUS_CANCELLED);

            foreach ($event->getTicketTiers() as $tier) {
                foreach ($tier->getSeatReservations() as $reservation) {
                    if ($reservation->getStatus() === SeatReservation::STATUS_PENDING) {
                        $reservation->setStatus(SeatReservation::STATUS_EXPIRED);
                    }
                }
            }

            foreach ($event->getBookings() as $booking) {
                if ($booking->getStatus() !== Booking::STATUS_CONFIRMED) {
                    continue;
                }

                $user = $booking->getUser();
                $user->setCreditBalance($user->getCreditBalance() + $booking->getTotalCredits());

                $transaction = new Transaction();
                $transaction
                    ->setUser($user)
                    ->setAmount($booking->getTotalCredits())
                    ->setType(Transaction::TYPE_REFUND)
                    ->setReference(sprintf('%s: #%d / booking #%d', $referencePrefix, $event->getId(), $booking->getId()));

                $this->em->persist($transaction);

                $booking->setStatus(Booking::STATUS_REFUNDED);

                // Accumulate refund data for post-commit messages
                $userId = (int) $user->getId();
                $refundMap[$userId] = ($refundMap[$userId] ?? 0) + $booking->getTotalCredits();
                ++$refundedBookings;
            }

            $this->em->flush();
        });

        // ── Dispatch async messages (after transaction is committed) ──────────────────
        if ($refundMap !== []) {
            // payment_queue: one audit entry per booking
            foreach ($event->getBookings() as $booking) {
                if ($booking->getStatus() !== Booking::STATUS_REFUNDED) {
                    continue;
                }

                $this->bus->dispatch(new RefundIssuedMessage(
                    userId:    (int) $booking->getUser()->getId(),
                    amount:    $booking->getTotalCredits(),
                    reason:    sprintf('%s: %s (event #%d)', $referencePrefix, $event->getName(), $event->getId()),
                    bookingId: (int) $booking->getId(),
                ));
            }

            // notification_queue: one bulk email per affected user
            $this->bus->dispatch(new EventCancelledMessage(
                eventId:   (int) $event->getId(),
                eventName: $event->getName(),
                refundMap: $refundMap,
            ));
        }

        // Bust event detail + events list so frontend immediately sees "cancelled" status
        $this->cache->invalidateEvent((int) $event->getId());

        return new CancellationResult(
            usersRefunded:   count($refundMap),
            creditsRefunded: (int) array_sum($refundMap),
        );
    }
}
