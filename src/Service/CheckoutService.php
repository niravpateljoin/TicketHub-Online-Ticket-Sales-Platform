<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\BookingItem;
use App\Entity\ETicket;
use App\Entity\SeatReservation;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AppException;
use App\Exception\CartException;
use App\Exception\Domain\InsufficientCreditsException;
use App\Exception\Domain\ReservationExpiredException;
use App\Exception\Domain\SeatSoldOutException;
use App\Message\Notification\BookingConfirmedMessage;
use App\Message\Ticket\GenerateETicketMessage;
use App\Repository\BookingRepository;
use App\Service\Cache\CacheService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Centralises all checkout concurrency safeguards:
 *
 *  1. Idempotency key dedup          (Challenge 4 — double-submit prevention)
 *  2. Pessimistic write-lock on User  (Challenge 4 — atomic credit deduction)
 *  3. Reservation expiry re-check     (Challenge 2 — soft lock / seat hold)
 *  4. Optimistic lock on TicketTier   (Challenge 1 — inventory race condition)
 *  5. Sale-window enforcement         (Challenge 5 — flash sale timing)
 *     ↑ delegated to CartService::getCheckoutContext() which calls assertTierCanBePurchased()
 *
 * The controller is responsible only for auth, rate-limiting, and HTTP response mapping.
 */
class CheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartService $cartService,
        private readonly PricingService $pricingService,
        private readonly BookingRepository $bookingRepository,
        private readonly MessageBusInterface $bus,
        private readonly CacheService $cache,
    ) {}

    /**
     * Processes checkout atomically.
     *
     * @return array{bookingId: int, totalCredits?: int, newCreditBalance?: int, items?: array, alreadyProcessed: bool}
     *
     * @throws CartException              on invalid cart / expired reservations / insufficient credits
     * @throws OptimisticLockException    on concurrent inventory modification (tier version mismatch)
     * @throws \RuntimeException          on unexpected state
     */
    public function process(User $user, string $idempotencyKey): array
    {
        // ── Challenge 4: Idempotency pre-check (optimistic, outside transaction) ──────────
        // Fast path: if we already have a booking for this key, return it immediately
        // without acquiring any locks.
        $existing = $this->bookingRepository->findOneBy([
            'user'           => $user,
            'idempotencyKey' => $idempotencyKey,
        ]);

        if ($existing instanceof Booking) {
            return [
                'bookingId'       => $existing->getId(),
                'alreadyProcessed' => true,
            ];
        }

        // ── Begin atomic transaction ──────────────────────────────────────────────────────
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            // ── Challenge 4: Pessimistic write-lock on User row ───────────────────────────
            // SELECT … FOR UPDATE prevents a second concurrent request from reading the
            // same credit balance before the first one has finished deducting it.
            /** @var User|null $lockedUser */
            $lockedUser = $this->em->find(User::class, $user->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$lockedUser instanceof User) {
                throw new \RuntimeException('Authenticated user could not be reloaded for checkout.');
            }

            // Re-fetch cart inside the locked context so we work with fresh data.
            // CartService::getCheckoutContext() also enforces:
            //   • non-empty cart
            //   • single-event cart
            //   • Challenge 2 re-check: reservation still pending & not expired
            //   • Challenge 5: tier sale-window check (assertTierCanBePurchased)
            $checkoutContext = $this->cartService->getCheckoutContext($lockedUser);

            /** @var SeatReservation[] $reservations */
            $reservations = $checkoutContext['reservations'];
            $totalCredits  = $checkoutContext['total'];

            // ── Credit balance check ──────────────────────────────────────────────────────
            // Read balance AFTER acquiring the pessimistic lock (not from the pre-lock $user).
            if ($lockedUser->getCreditBalance() < $totalCredits) {
                throw new InsufficientCreditsException($totalCredits, $lockedUser->getCreditBalance());
            }

            // ── Challenge 1: Optimistic lock on each TicketTier ───────────────────────────
            // Tell Doctrine to verify the version hasn't changed by the time we flush.
            // If another transaction committed a soldCount update between our SELECT and our
            // UPDATE, Doctrine will throw OptimisticLockException.
            $reservationIds = [];
            $tierQuantities = [];

            foreach ($reservations as $reservation) {
                // Challenge 2: Explicit expiry re-check (belt-and-suspenders after getCheckoutContext).
                if ($reservation->getStatus() !== SeatReservation::STATUS_PENDING || $reservation->isExpired()) {
                    throw new ReservationExpiredException();
                }

                $reservationIds[] = (int) $reservation->getId();

                $tier   = $reservation->getTicketTier();
                $tierId = (int) $tier->getId();

                // Register optimistic version check; throws OptimisticLockException on flush
                // if the version column was updated by a concurrent transaction.
                $this->em->lock($tier, LockMode::OPTIMISTIC, $tier->getVersion());

                $tierQuantities[$tierId] = ($tierQuantities[$tierId] ?? 0) + $reservation->getQuantity();
            }

            // Available-seat check (excluding our own reservations so they don't count
            // against themselves).
            foreach ($tierQuantities as $tierId => $quantity) {
                $matchingTier = null;
                foreach ($reservations as $reservation) {
                    if ((int) $reservation->getTicketTier()->getId() === $tierId) {
                        $matchingTier = $reservation->getTicketTier();
                        break;
                    }
                }

                if ($matchingTier === null) {
                    continue;
                }

                $available = $this->cartService->getTierAvailableSeats($matchingTier, $reservationIds);
                if ($available < $quantity) {
                    throw new SeatSoldOutException($matchingTier->getName());
                }
            }

            // ── Build Booking ─────────────────────────────────────────────────────────────
            $booking = new Booking();
            $booking
                ->setUser($lockedUser)
                ->setEvent($reservations[0]->getTicketTier()->getEvent())
                ->setTotalCredits($totalCredits)
                ->setStatus(Booking::STATUS_CONFIRMED)
                ->setIdempotencyKey($idempotencyKey);   // DB unique constraint catches race here

            $this->em->persist($booking);

            // ── Build BookingItems, ETickets, confirm reservations, update soldCount ─────
            $lineItems = [];
            $eTickets  = [];

            foreach ($reservations as $reservation) {
                $tier      = $reservation->getTicketTier();
                $unitPrice = $this->pricingService->calculateFinalPrice($tier->getBasePrice());

                $bookingItem = new BookingItem();
                $bookingItem
                    ->setBooking($booking)
                    ->setTicketTier($tier)
                    ->setSeatReservation($reservation)
                    ->setQuantity($reservation->getQuantity())
                    ->setUnitPrice($unitPrice);

                $this->em->persist($bookingItem);

                // Create ETicket immediately (filePath + generatedAt are null until PDF is ready).
                $eTicket = new ETicket();
                $eTicket->setBookingItem($bookingItem);
                $this->em->persist($eTicket);
                $eTickets[] = $eTicket;

                $reservation->setStatus(SeatReservation::STATUS_CONFIRMED);
                $tier->setSoldCount($tier->getSoldCount() + $reservation->getQuantity());

                $lineItems[] = [
                    'reservationId' => $reservation->getId(),
                    'tierId'        => $tier->getId(),
                    'tierName'      => $tier->getName(),
                    'quantity'      => $reservation->getQuantity(),
                    'unitPrice'     => $unitPrice,
                    'subtotal'      => $unitPrice * $reservation->getQuantity(),
                    'qrToken'       => $eTicket->getQrToken(),
                ];
            }

            // ── Deduct credits ────────────────────────────────────────────────────────────
            $lockedUser->setCreditBalance($lockedUser->getCreditBalance() - $totalCredits);

            // ── Create transaction record ─────────────────────────────────────────────────
            $transaction = new Transaction();
            $transaction
                ->setUser($lockedUser)
                ->setAmount($totalCredits)
                ->setType(Transaction::TYPE_DEBIT)
                ->setReference(sprintf('Booking #%s', $idempotencyKey));

            $this->em->persist($transaction);

            // flush() triggers the optimistic version check here — any version mismatch
            // throws OptimisticLockException before the COMMIT reaches PostgreSQL.
            $this->em->flush();
            $connection->commit();

            // ── Bust caches (soldCount changed, event listing is stale) ───────────────
            $eventId = (int) $booking->getEvent()->getId();
            $this->cache->invalidateEvent($eventId);

            // ── Dispatch async messages (outside the DB transaction) ─────────────────
            // These return instantly — workers consume them independently via RabbitMQ.

            // ticket_queue: one PDF generation job per ETicket
            foreach ($eTickets as $eTicket) {
                $this->bus->dispatch(new GenerateETicketMessage(
                    eTicketId: $eTicket->getId(),
                    bookingId: $booking->getId(),
                ));
            }

            // notification_queue: booking confirmation email
            $this->bus->dispatch(new BookingConfirmedMessage(
                bookingId: $booking->getId(),
                userEmail: $lockedUser->getEmail(),
            ));

            return [
                'bookingId'        => $booking->getId(),
                'totalCredits'     => $totalCredits,
                'newCreditBalance' => $lockedUser->getCreditBalance(),
                'items'            => $lineItems,
                'alreadyProcessed' => false,
            ];

        } catch (AppException $e) {
            $connection->rollBack();
            throw $e;

        } catch (OptimisticLockException $e) {
            // Challenge 1: Another transaction updated the TicketTier version before us.
            // The client should refresh and try again.
            $connection->rollBack();
            throw $e;

        } catch (UniqueConstraintViolationException $e) {
            // Challenge 4: Two concurrent requests with the same idempotency key both
            // passed the pre-check but raced to INSERT. The loser hits this exception.
            // Re-query to return the winner's booking rather than surfacing a DB error.
            $connection->rollBack();

            $existing = $this->bookingRepository->findOneBy([
                'user'           => $user,
                'idempotencyKey' => $idempotencyKey,
            ]);

            if ($existing instanceof Booking) {
                return [
                    'bookingId'        => $existing->getId(),
                    'alreadyProcessed' => true,
                ];
            }

            // Constraint violation on a different column — re-throw.
            throw new \RuntimeException('A database constraint was violated during checkout.', 0, $e);

        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
