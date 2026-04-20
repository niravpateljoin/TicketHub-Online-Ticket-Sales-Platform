<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Exception\CartException;
use App\Exception\Domain\EventCancelledException;
use App\Exception\Domain\ReservationExpiredException;
use App\Exception\Domain\SaleWindowException;
use App\Exception\Domain\SeatSoldOutException;
use App\Repository\SeatReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartService
{
    public function __construct(
        private readonly SeatReservationRepository $seatReservationRepository,
        private readonly EntityManagerInterface $em,
        private readonly ApiDataTransformer $transformer,
        private readonly PricingService $pricingService,
    ) {}

    public function addToCart(User $user, TicketTier $tier, int $quantity): SeatReservation
    {
        if ($quantity <= 0) {
            throw new CartException('Quantity must be at least 1.', 422);
        }

        $this->expireUserReservations($user);
        $this->assertTierCanBeReserved($tier, $quantity);
        $this->assertSingleEventCart($user, $tier->getEvent());

        $reservation = new SeatReservation();
        $reservation
            ->setUser($user)
            ->setTicketTier($tier)
            ->setQuantity($quantity)
            ->setStatus(SeatReservation::STATUS_PENDING)
            ->setExpiresAt(new \DateTime('+10 minutes'));

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    public function getCartItems(User $user): array
    {
        return array_map(
            fn (SeatReservation $reservation): array => $this->transformer->cartItem($reservation),
            $this->getActiveReservations($user)
        );
    }

    public function getCartTotal(User $user): int
    {
        $total = 0;

        foreach ($this->getActiveReservations($user) as $reservation) {
            $total += $this->pricingService->calculateFinalPrice($reservation->getTicketTier()->getBasePrice()) * $reservation->getQuantity();
        }

        return $total;
    }

    public function removeFromCart(User $user, int $reservationId): void
    {
        $reservation = $this->seatReservationRepository->find($reservationId);

        if (!$reservation instanceof SeatReservation || $reservation->getUser()->getId() !== $user->getId()) {
            throw new CartException('Cart item not found.', 404);
        }

        if ($reservation->getStatus() !== SeatReservation::STATUS_PENDING) {
            throw new CartException('Cart item is no longer active.');
        }

        $reservation->setStatus(SeatReservation::STATUS_EXPIRED);
        $this->em->flush();
    }

    public function clearCart(User $user): void
    {
        foreach ($this->getActiveReservations($user) as $reservation) {
            $reservation->setStatus(SeatReservation::STATUS_EXPIRED);
        }

        $this->em->flush();
    }

    /**
     * @return SeatReservation[]
     */
    public function getActiveReservations(User $user): array
    {
        $this->expireUserReservations($user);

        return $this->seatReservationRepository->findActiveCartReservationsForUser($user);
    }

    /**
     * @return array{reservations: SeatReservation[], total: int}
     */
    public function getCheckoutContext(User $user): array
    {
        $reservations = $this->getActiveReservations($user);

        if ($reservations === []) {
            throw new CartException('Your cart is empty.');
        }

        $eventId = $reservations[0]->getTicketTier()->getEvent()->getId();
        $total = 0;

        foreach ($reservations as $reservation) {
            if ($reservation->getStatus() !== SeatReservation::STATUS_PENDING || $reservation->isExpired()) {
                throw new ReservationExpiredException();
            }

            $tier = $reservation->getTicketTier();
            if ($tier->getEvent()->getId() !== $eventId) {
                throw new CartException('Your cart can only contain tickets for one event at a time.');
            }

            $this->assertTierCanBePurchased($tier);
            $total += $this->pricingService->calculateFinalPrice($tier->getBasePrice()) * $reservation->getQuantity();
        }

        return [
            'reservations' => $reservations,
            'total' => $total,
        ];
    }

    public function buildCartPayload(User $user, ?string $idempotencyKey = null): array
    {
        $items = $this->getCartItems($user);
        $total = array_sum(array_map(static fn (array $item): int => $item['subtotal'], $items));
        $soonestExpiresAt = null;

        foreach ($items as $item) {
            $expiresAt = $item['expiresAt'] ?? null;
            if ($expiresAt === null) {
                continue;
            }

            if ($soonestExpiresAt === null || $expiresAt < $soonestExpiresAt) {
                $soonestExpiresAt = $expiresAt;
            }
        }

        $payload = [
            'items' => $items,
            'itemCount' => array_sum(array_map(static fn (array $item): int => $item['quantity'], $items)),
            'total' => $total,
            'creditBalance' => $user->getCreditBalance(),
            'creditsAfterPurchase' => $user->getCreditBalance() - $total,
            'sufficient' => $user->getCreditBalance() >= $total,
            'expiresAt' => $soonestExpiresAt,
        ];

        if ($items !== []) {
            $payload['event'] = [
                'id' => $items[0]['eventId'],
                'name' => $items[0]['eventName'],
            ];
        }

        if ($idempotencyKey !== null) {
            $payload['idempotencyKey'] = $idempotencyKey;
        }

        return $payload;
    }

    public function getTierAvailableSeats(TicketTier $tier, array $excludedReservationIds = []): int
    {
        $activeReservations = $this->seatReservationRepository->sumActiveReservedQuantityForTier($tier, $excludedReservationIds);

        return max(0, $tier->getTotalSeats() - $tier->getSoldCount() - $activeReservations);
    }

    private function expireUserReservations(User $user): void
    {
        $expiredReservations = $this->seatReservationRepository->findExpiredPendingReservationsForUser($user);

        if ($expiredReservations === []) {
            return;
        }

        foreach ($expiredReservations as $reservation) {
            $reservation->setStatus(SeatReservation::STATUS_EXPIRED);
        }

        $this->em->flush();
    }

    private function assertSingleEventCart(User $user, Event $event): void
    {
        foreach ($this->seatReservationRepository->findActiveCartReservationsForUser($user) as $reservation) {
            if ($reservation->getTicketTier()->getEvent()->getId() !== $event->getId()) {
                throw new CartException('Your cart can only contain tickets for one event at a time.');
            }
        }
    }

    private function assertTierCanBeReserved(TicketTier $tier, int $quantity): void
    {
        $this->assertTierCanBePurchased($tier);

        if ($this->getTierAvailableSeats($tier) < $quantity) {
            throw new SeatSoldOutException($tier->getName());
        }
    }

    private function assertTierCanBePurchased(TicketTier $tier): void
    {
        $event = $tier->getEvent();
        $now = new \DateTimeImmutable();

        if ($event->getStatus() === Event::STATUS_CANCELLED) {
            throw new EventCancelledException();
        }

        if ($event->getStatus() !== Event::STATUS_ACTIVE || $event->getDateTime() <= $now) {
            throw new CartException('This event is no longer available for booking.', 409);
        }

        if ($tier->getSaleStartsAt() !== null && $tier->getSaleStartsAt() > $now) {
            throw new SaleWindowException(sprintf('Ticket sales for "%s" haven\'t started yet.', $tier->getName()));
        }

        if ($tier->getSaleEndsAt() !== null && $tier->getSaleEndsAt() < $now) {
            throw new SaleWindowException(sprintf('Ticket sales for "%s" have ended.', $tier->getName()));
        }
    }
}
