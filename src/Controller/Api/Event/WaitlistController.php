<?php

namespace App\Controller\Api\Event;

use App\Controller\Api\ApiController;
use App\Entity\Waitlist;
use App\Repository\EventRepository;
use App\Repository\TicketTierRepository;
use App\Repository\WaitlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class WaitlistController extends ApiController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly TicketTierRepository $ticketTierRepository,
        private readonly WaitlistRepository $waitlistRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/events/{eventId}/tiers/{tierId}/waitlist', name: 'api_waitlist_join', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function join(int $eventId, int $tierId): JsonResponse
    {
        $event = $this->eventRepository->find($eventId);
        $tier  = $this->ticketTierRepository->find($tierId);

        if ($event === null || $tier === null || $tier->getEvent()->getId() !== $event->getId()) {
            return $this->error('Event or tier not found.', 404);
        }

        if ($event->getStatus() === 'cancelled') {
            return $this->error('Cannot join waitlist for a cancelled event.', 422);
        }

        $existing = $this->waitlistRepository->findPendingByUserAndTier($this->getUser(), $tier);
        if ($existing !== null) {
            return $this->error('You are already on the waitlist for this tier.', 409);
        }

        $entry = new Waitlist();
        $entry->setUser($this->getUser());
        $entry->setEvent($event);
        $entry->setTicketTier($tier);

        $this->em->persist($entry);
        $this->em->flush();

        return $this->success([
            'id'       => $entry->getId(),
            'status'   => $entry->getStatus(),
            'joinedAt' => $entry->getJoinedAt()->format(\DateTimeInterface::ATOM),
        ], 201, 'You have been added to the waitlist. We\'ll notify you when seats become available.');
    }

    #[Route('/events/{eventId}/tiers/{tierId}/waitlist', name: 'api_waitlist_leave', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function leave(int $eventId, int $tierId): JsonResponse
    {
        $tier  = $this->ticketTierRepository->find($tierId);

        if ($tier === null) {
            return $this->error('Tier not found.', 404);
        }

        $entry = $this->waitlistRepository->findPendingByUserAndTier($this->getUser(), $tier);

        if ($entry === null) {
            return $this->error('You are not on the waitlist for this tier.', 404);
        }

        $entry->setStatus(Waitlist::STATUS_CANCELLED);
        $this->em->flush();

        return $this->success([], message: 'You have been removed from the waitlist.');
    }

    #[Route('/user/waitlist', name: 'api_user_waitlist', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myWaitlist(Request $request): JsonResponse
    {
        $entries = $this->waitlistRepository->findByUserOrderedByDate($this->getUser());

        $data = array_map(fn (Waitlist $w): array => [
            'id'         => $w->getId(),
            'status'     => $w->getStatus(),
            'joinedAt'   => $w->getJoinedAt()->format(\DateTimeInterface::ATOM),
            'notifiedAt' => $w->getNotifiedAt()?->format(\DateTimeInterface::ATOM),
            'event' => [
                'id'       => $w->getEvent()->getId(),
                'name'     => $w->getEvent()->getName(),
                'slug'     => $w->getEvent()->getSlug(),
                'dateTime' => $w->getEvent()->getDateTime()->format(\DateTimeInterface::ATOM),
                'status'   => $w->getEvent()->getStatus(),
            ],
            'tier' => [
                'id'   => $w->getTicketTier()->getId(),
                'name' => $w->getTicketTier()->getName(),
            ],
        ], $entries);

        return $this->success($data);
    }

    #[Route('/events/{eventId}/tiers/{tierId}/waitlist/status', name: 'api_waitlist_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(int $eventId, int $tierId): JsonResponse
    {
        $tier  = $this->ticketTierRepository->find($tierId);
        $entry = $tier ? $this->waitlistRepository->findPendingByUserAndTier($this->getUser(), $tier) : null;

        return $this->success(['onWaitlist' => $entry !== null]);
    }
}
