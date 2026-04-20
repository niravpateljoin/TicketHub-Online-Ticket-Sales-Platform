<?php

namespace App\Controller\Api\Organizer;

use App\Controller\Api\ApiController;
use App\Entity\Booking;
use App\Entity\Event;
use App\Entity\Organizer;
use App\Entity\User;
use App\Repository\BookingItemRepository;
use App\Repository\BookingRepository;
use App\Repository\EventRepository;
use App\Repository\OrganizerRepository;
use App\Security\Voter\EventVoter;
use App\Service\ApiDataTransformer;
use App\Service\Cache\CacheService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/organizer')]
class RevenueController extends ApiController
{
    public function __construct(
        private readonly OrganizerRepository $organizerRepository,
        private readonly EventRepository $eventRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly BookingItemRepository $bookingItemRepository,
        private readonly ApiDataTransformer $transformer,
        private readonly CacheService $cache,
    ) {}

    #[Route('/stats', name: 'api_organizer_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $organizer = $this->getCurrentOrganizer();

        $result = $this->cache->getOrganizerStats(
            (int) $organizer->getId(),
            function () use ($organizer): array {
                $events = $this->eventRepository->findBy(['organizer' => $organizer]);

                $totalEvents      = count($events);
                $activeEvents     = 0;
                $soldOutEvents    = 0;
                $pastEvents       = 0;
                $bookingsReceived = 0;
                $ticketsSold      = 0;
                $grossRevenue     = 0;
                $now              = new \DateTimeImmutable();

                foreach ($events as $event) {
                    if ($event->getDateTime() < $now) {
                        ++$pastEvents;
                    }

                    if ($event->getStatus() === Event::STATUS_ACTIVE) {
                        ++$activeEvents;
                    }

                    if ($event->getStatus() === Event::STATUS_SOLD_OUT) {
                        ++$soldOutEvents;
                    }

                    foreach ($event->getBookings() as $booking) {
                        if ($booking->getStatus() !== Booking::STATUS_CONFIRMED) {
                            continue;
                        }

                        ++$bookingsReceived;
                        $grossRevenue += $booking->getTotalCredits();
                    }

                    $ticketsSold += $this->transformer->countEventTicketsSold($event);
                }

                $netRevenue = (int) round($grossRevenue * 0.99);
                $systemFee  = $grossRevenue - $netRevenue;

                return [
                    'totalEvents'      => $totalEvents,
                    'activeEvents'     => $activeEvents,
                    'soldOutEvents'    => $soldOutEvents,
                    'pastEvents'       => $pastEvents,
                    'bookingsReceived' => $bookingsReceived,
                    'ticketsSold'      => $ticketsSold,
                    'grossRevenue'     => $grossRevenue,
                    'systemFee'        => $systemFee,
                    'netRevenue'       => $netRevenue,
                ];
            }
        );

        return $this->success($result);
    }

    #[Route('/revenue', name: 'api_organizer_revenue_legacy', methods: ['GET'])]
    public function summaryRevenue(): JsonResponse
    {
        return $this->stats();
    }

    #[Route('/events/{id}/revenue', name: 'api_organizer_events_revenue', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function eventRevenue(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event instanceof Event) {
            return $this->error('Event not found.', 404);
        }

        $this->denyAccessUnlessGranted(EventVoter::EVENT_EDIT, $event);

        $organizer = $this->getCurrentOrganizer();

        $result = $this->cache->getOrganizerEventRevenue(
            $id,
            (int) $organizer->getId(),
            function () use ($event): array {
                $grossRevenue = 0;
                $ticketsSold  = 0;
                $tiers        = [];

                foreach ($event->getTicketTiers() as $tier) {
                    $tierRevenue = 0;
                    $tierTickets = 0;

                    foreach ($tier->getBookingItems() as $item) {
                        if ($item->getBooking()->getStatus() !== Booking::STATUS_CONFIRMED) {
                            continue;
                        }

                        $tierRevenue += $item->getSubtotal();
                        $tierTickets += $item->getQuantity();
                    }

                    $grossRevenue += $tierRevenue;
                    $ticketsSold  += $tierTickets;
                    $tiers[] = [
                        'id'           => $tier->getId(),
                        'name'         => $tier->getName(),
                        'ticketsSold'  => $tierTickets,
                        'grossRevenue' => $tierRevenue,
                    ];
                }

                $systemFee = $grossRevenue - (int) round($grossRevenue * 0.99);

                return [
                    'event'        => $this->transformer->eventSummary($event),
                    'grossRevenue' => $grossRevenue,
                    'systemFee'    => $systemFee,
                    'netRevenue'   => $grossRevenue - $systemFee,
                    'ticketsSold'  => $ticketsSold,
                    'tiers'        => $tiers,
                ];
            }
        );

        return $this->success($result);
    }

    private function getCurrentOrganizer(): Organizer
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->organizerRepository->findOneBy(['user' => $user]);
    }
}
