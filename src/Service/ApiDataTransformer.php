<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\BookingItem;
use App\Entity\Category;
use App\Entity\ErrorLog;
use App\Entity\Event;
use App\Entity\Organizer;
use App\Entity\SeatReservation;
use App\Entity\TicketTier;
use App\Entity\User;
use App\Repository\SeatReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

class ApiDataTransformer
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly SeatReservationRepository $seatReservationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function user(User $user, ?Organizer $organizer = null): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'pendingEmail' => $user->getPendingEmail(),
            'roles' => $user->getRoles(),
            'role' => $user->getRole(),
            'creditBalance' => $user->getCreditBalance(),
            'isVerified' => $user->isVerified(),
            'verificationStatus' => $user->isVerified() ? 'verified' : 'pending',
            'verifiedAt' => $user->getVerifiedAt()?->format(\DateTimeInterface::ATOM),
            'approvalStatus' => $organizer?->getApprovalStatus(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    public function category(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $this->slugify($category->getName()),
        ];
    }

    public function categoryWithCount(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $this->slugify($category->getName()),
            'eventCount' => $category->getEvents()->count(),
        ];
    }

    public function eventSummary(Event $event): array
    {
        $tiers = $event->getTicketTiers()->toArray();
        $lowestPrice = null;
        $totalSeats = 0;
        $tierSoldTickets = 0;

        foreach ($tiers as $tier) {
            if (!$tier instanceof TicketTier) {
                continue;
            }

            $finalPrice = $this->pricingService->calculateFinalPrice($tier->getBasePrice());
            $lowestPrice = $lowestPrice === null ? $finalPrice : min($lowestPrice, $finalPrice);
            $totalSeats += $tier->getTotalSeats();
            $tierSoldTickets += $tier->getSoldCount();
        }

        $soldTickets = max($tierSoldTickets, $this->countEventTicketsSold($event));

        return [
            'id' => $event->getId(),
            'slug' => $event->getSlug(),
            'name' => $event->getName(),
            'description' => $event->getDescription(),
            'category' => $event->getCategory()->getName(),
            'categoryData' => $this->category($event->getCategory()),
            'dateTime' => $event->getDateTime()->format(\DateTimeInterface::ATOM),
            'startDate' => $event->getDateTime()->format(\DateTimeInterface::ATOM),
            'startTime' => $event->getDateTime()->format('H:i'),
            'venueName' => $event->getVenueName(),
            'venueAddress' => $event->getVenueAddress(),
            'isOnline' => $event->isOnline(),
            'bannerUrl' => $event->getBannerImageName() !== null ? sprintf('/api/events/%d/banner', $event->getId()) : null,
            'status' => $event->getStatus(),
            'organizerName' => $event->getOrganizer()->getUser()->getEmail(),
            'organizer' => [
                'id' => $event->getOrganizer()->getId(),
                'name' => $event->getOrganizer()->getUser()->getEmail(),
                'email' => $event->getOrganizer()->getUser()->getEmail(),
            ],
            'lowestPrice' => $lowestPrice,
            'totalSeats' => $totalSeats,
            'soldTickets' => $soldTickets,
        ];
    }

    public function eventDetail(Event $event): array
    {
        $payload = $this->eventSummary($event);
        $payload['tiers'] = array_map(
            fn (TicketTier $tier): array => $this->tier($tier),
            $event->getTicketTiers()->toArray()
        );

        return $payload;
    }

    public function tier(TicketTier $tier): array
    {
        $finalPrice = $this->pricingService->calculateFinalPrice($tier->getBasePrice());
        $availableSeats = $this->getTierAvailableSeats($tier);
        $now = new \DateTimeImmutable();

        $status = 'available';
        if ($tier->getEvent()->getStatus() !== Event::STATUS_ACTIVE || $tier->getEvent()->getDateTime() <= $now) {
            $status = 'unavailable';
        } elseif ($tier->getSaleStartsAt() !== null && $tier->getSaleStartsAt() > $now) {
            $status = 'upcoming';
        } elseif ($tier->getSaleEndsAt() !== null && $tier->getSaleEndsAt() < $now) {
            $status = 'closed';
        } elseif ($availableSeats <= 0) {
            $status = 'sold_out';
        }

        return [
            'id' => $tier->getId(),
            'name' => $tier->getName(),
            'description' => null,
            'basePrice' => $tier->getBasePrice(),
            'finalPrice' => $finalPrice,
            'price' => $finalPrice,
            'totalSeats' => $tier->getTotalSeats(),
            'soldCount' => $tier->getSoldCount(),
            'availableSeats' => $availableSeats,
            'saleStartsAt' => $tier->getSaleStartsAt()?->format(\DateTimeInterface::ATOM),
            'saleEndsAt' => $tier->getSaleEndsAt()?->format(\DateTimeInterface::ATOM),
            'status' => $status,
        ];
    }

    public function cartItem(SeatReservation $reservation): array
    {
        $tier = $reservation->getTicketTier();
        $event = $tier->getEvent();
        $basePrice = $tier->getBasePrice();
        $unitPrice = $this->pricingService->calculateFinalPrice($basePrice);

        return [
            'reservationId' => $reservation->getId(),
            'eventId' => $event->getId(),
            'eventName' => $event->getName(),
            'eventDateTime' => $event->getDateTime()->format(\DateTimeInterface::ATOM),
            'tierId' => $tier->getId(),
            'tierName' => $tier->getName(),
            'quantity' => $reservation->getQuantity(),
            'basePrice' => $basePrice,
            'systemFee' => $this->pricingService->calculateSystemFee($basePrice),
            'unitPrice' => $unitPrice,
            'price' => $unitPrice,
            'subtotal' => $unitPrice * $reservation->getQuantity(),
            'totalCredits' => $unitPrice * $reservation->getQuantity(),
            'status' => $reservation->isExpired() ? SeatReservation::STATUS_EXPIRED : $reservation->getStatus(),
            'reservedAt' => $reservation->getReservedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $reservation->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    public function booking(Booking $booking): array
    {
        return [
            'id' => $booking->getId(),
            'event' => $this->eventSummary($booking->getEvent()),
            'userId' => $booking->getUser()->getId(),
            'userEmail' => $booking->getUser()->getEmail(),
            'items' => array_map(
                fn (BookingItem $item): array => $this->bookingItem($item),
                $booking->getBookingItems()->toArray()
            ),
            'totalCredits' => $booking->getTotalCredits(),
            'status' => $booking->getStatus(),
            'idempotencyKey' => $booking->getIdempotencyKey(),
            'createdAt' => $booking->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    public function organizer(Organizer $organizer): array
    {
        $user = $organizer->getUser();
        $status = $organizer->getDeactivatedAt() !== null ? 'deactivated' : $organizer->getApprovalStatus();

        return [
            'id' => $organizer->getId(),
            'email' => $user->getEmail(),
            'organizationName' => $user->getEmail(),
            'approvalStatus' => $organizer->getApprovalStatus(),
            'status' => $status,
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'approvedAt' => $organizer->getApprovedAt()?->format(\DateTimeInterface::ATOM),
            'deactivatedAt' => $organizer->getDeactivatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function errorLog(ErrorLog $errorLog): array
    {
        return [
            'id'             => $errorLog->getId(),
            'message'        => $errorLog->getMessage(),
            'exceptionClass' => $errorLog->getExceptionClass(),
            'stackTrace'     => $errorLog->getStackTrace(),
            'route'          => $errorLog->getRoute(),
            'method'         => $errorLog->getMethod(),
            'statusCode'     => $errorLog->getStatusCode(),
            'userId'         => $errorLog->getUserId(),
            'ipAddress'      => $errorLog->getIpAddress(),
            'occurredAt'     => $errorLog->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'createdAt'      => $errorLog->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'resolved'       => $errorLog->isResolved(),
            'adminNote'      => $errorLog->getAdminNote(),
        ];
    }

    public function getTierAvailableSeats(TicketTier $tier): int
    {
        $activeReservations = (int) $this->seatReservationRepository->createQueryBuilder('reservation')
            ->select('COALESCE(SUM(reservation.quantity), 0)')
            ->andWhere('reservation.ticketTier = :tier')
            ->andWhere('reservation.status = :status')
            ->andWhere('reservation.expiresAt > :now')
            ->setParameter('tier', $tier)
            ->setParameter('status', SeatReservation::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        return max(0, $tier->getTotalSeats() - $tier->getSoldCount() - $activeReservations);
    }

    public function countEventTicketsSold(Event $event): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(item.quantity), 0)')
            ->from(BookingItem::class, 'item')
            ->join('item.booking', 'booking')
            ->where('booking.event = :event')
            ->andWhere('booking.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', Booking::STATUS_CONFIRMED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function bookingItem(BookingItem $item): array
    {
        return [
            'id' => $item->getId(),
            'tierId' => $item->getTicketTier()->getId(),
            'tierName' => $item->getTicketTier()->getName(),
            'quantity' => $item->getQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'subtotal' => $item->getSubtotal(),
            'totalCredits' => $item->getSubtotal(),
            'qrToken' => $item->getETicket()?->getQrToken(),
        ];
    }

    private function slugify(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($value, " \t\n\r\0\x0B")));
    }
}
