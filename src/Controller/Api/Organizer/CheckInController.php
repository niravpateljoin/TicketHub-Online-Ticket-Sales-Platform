<?php

namespace App\Controller\Api\Organizer;

use App\Controller\Api\ApiController;
use App\Repository\ETicketRepository;
use App\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/organizer')]
#[IsGranted('ROLE_ORGANIZER')]
class CheckInController extends ApiController
{
    public function __construct(
        private readonly ETicketRepository $eTicketRepository,
        private readonly OrganizerRepository $organizerRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/checkin', name: 'api_organizer_checkin', methods: ['POST'])]
    public function checkIn(Request $request): JsonResponse
    {
        $body     = json_decode((string) $request->getContent(), true) ?? [];
        $qrToken  = trim((string) ($body['qrToken'] ?? ''));

        if ($qrToken === '') {
            return $this->error('QR token is required.', 422);
        }

        $ticket = $this->eTicketRepository->findOneBy(['qrToken' => $qrToken]);

        if ($ticket === null) {
            return $this->error('Invalid QR code — ticket not found.', 404);
        }

        // Verify organizer owns this event
        $organizer = $this->organizerRepository->findOneBy(['user' => $this->getUser()]);
        $event     = $ticket->getBookingItem()->getBooking()->getEvent();

        if ($organizer === null || $event->getOrganizer()->getId() !== $organizer->getId()) {
            return $this->error('You do not have permission to check in tickets for this event.', 403);
        }

        if ($ticket->isCheckedIn()) {
            return $this->error(
                sprintf('Ticket already checked in at %s.', $ticket->getCheckedInAt()->format('d M Y, H:i')),
                409
            );
        }

        $booking = $ticket->getBookingItem()->getBooking();
        if ($booking->getStatus() !== 'confirmed') {
            return $this->error('This ticket belongs to a booking that is no longer valid.', 422);
        }

        $ticket->setCheckedInAt(new \DateTime());
        $ticket->setCheckedInBy($this->getUser());
        $this->em->flush();

        $item = $ticket->getBookingItem();

        return $this->success([
            'qrToken'       => $ticket->getQrToken(),
            'checkedInAt'   => $ticket->getCheckedInAt()->format(\DateTimeInterface::ATOM),
            'attendeeEmail' => $booking->getUser()->getEmail(),
            'attendeeName'  => $booking->getUser()->getName(),
            'eventName'     => $event->getName(),
            'tierName'      => $item->getTicketTier()->getName(),
            'quantity'      => $item->getQuantity(),
            'bookingId'     => $booking->getId(),
        ], message: 'Check-in successful.');
    }

    #[Route('/checkin/history', name: 'api_organizer_checkin_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $organizer = $this->organizerRepository->findOneBy(['user' => $this->getUser()]);

        if ($organizer === null) {
            return $this->error('Organizer profile not found.', 404);
        }

        $eventId = $request->query->getInt('eventId');

        $qb = $this->eTicketRepository->createQueryBuilder('t')
            ->join('t.bookingItem', 'item')
            ->join('item.booking', 'booking')
            ->join('booking.event', 'event')
            ->join('event.organizer', 'org')
            ->addSelect('item', 'booking', 'event')
            ->andWhere('org.id = :orgId')
            ->andWhere('t.checkedInAt IS NOT NULL')
            ->setParameter('orgId', $organizer->getId())
            ->orderBy('t.checkedInAt', 'DESC')
            ->setMaxResults(100);

        if ($eventId > 0) {
            $qb->andWhere('event.id = :eventId')->setParameter('eventId', $eventId);
        }

        $tickets = $qb->getQuery()->getResult();

        $rows = array_map(function ($t) {
            $item    = $t->getBookingItem();
            $booking = $item->getBooking();
            return [
                'qrToken'       => $t->getQrToken(),
                'checkedInAt'   => $t->getCheckedInAt()->format(\DateTimeInterface::ATOM),
                'attendeeEmail' => $booking->getUser()->getEmail(),
                'attendeeName'  => $booking->getUser()->getName(),
                'eventName'     => $booking->getEvent()->getName(),
                'tierName'      => $item->getTicketTier()->getName(),
                'quantity'      => $item->getQuantity(),
            ];
        }, $tickets);

        return $this->success($rows);
    }
}
