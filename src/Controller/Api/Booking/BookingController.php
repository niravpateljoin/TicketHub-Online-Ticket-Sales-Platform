<?php

namespace App\Controller\Api\Booking;

use App\Controller\Api\ApiController;
use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Security\Voter\BookingVoter;
use App\Service\ApiDataTransformer;
use App\Service\ETicketGeneratorService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/bookings')]
class BookingController extends ApiController
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly ApiDataTransformer $transformer,
    ) {}

    #[Route('', name: 'api_bookings_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(50, $request->query->getInt('perPage', 10)));

        $qb = $this->bookingRepository->createQueryBuilder('booking')
            ->join('booking.event', 'event')
            ->addSelect('event')
            ->andWhere('booking.user = :user')
            ->setParameter('user', $user)
            ->orderBy('booking.createdAt', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT booking.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $bookings = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->paginated(
            array_map(fn (Booking $booking): array => $this->transformer->booking($booking), $bookings),
            $page,
            $total,
            $perPage
        );
    }

    #[Route('/{id}', name: 'api_bookings_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $booking = $this->bookingRepository->find($id);

        if ($booking === null) {
            return $this->error('Booking not found.', 404);
        }

        $this->denyAccessUnlessGranted(BookingVoter::BOOKING_VIEW, $booking);

        return $this->success($this->transformer->booking($booking));
    }

    #[Route('/{id}/ticket', name: 'api_bookings_ticket', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ticket(int $id, ETicketGeneratorService $generator): Response
    {
        $booking = $this->bookingRepository->find($id);

        if ($booking === null) {
            return $this->error('Booking not found.', 404);
        }

        $this->denyAccessUnlessGranted(BookingVoter::TICKET_DOWNLOAD, $booking);

        $eTickets = [];
        foreach ($booking->getBookingItems() as $item) {
            $eTicket = $item->getETicket();
            if ($eTicket !== null) {
                if (!$eTicket->isPdfReady()) {
                    $generator->generate($eTicket);
                }
                $eTickets[] = $eTicket;
            }
        }

        if (empty($eTickets)) {
            return $this->error('No tickets available yet.', 404);
        }

        if (count($eTickets) === 1) {
            $response = new BinaryFileResponse($eTickets[0]->getFilePath());
            $response->headers->set('Content-Type', 'application/pdf');
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $this->buildTicketFileName($booking, false)
            );
            return $response;
        }

        $zipPath = sys_get_temp_dir() . '/booking-' . $booking->getId() . '-tickets.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($eTickets as $i => $eTicket) {
            $zip->addFile($eTicket->getFilePath(), sprintf('ticket-%d.pdf', $i + 1));
        }
        $zip->close();

        $response = new BinaryFileResponse($zipPath, 200, [], false);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->buildTicketFileName($booking, true)
        );
        $response->deleteFileAfterSend(true);
        return $response;
    }

    private function buildTicketFileName(Booking $booking, bool $isZip): string
    {
        $displayName = $booking->getUser()->getName();
        if ($displayName === null || trim($displayName) === '') {
            $displayName = explode('@', $booking->getUser()->getEmail())[0] ?? 'user';
        }

        $userCode = $this->sanitizeSegment($displayName, 12, 'user');

        $eventCodeRaw = $booking->getEvent()->getSlug();
        if ($eventCodeRaw === '') {
            $eventCodeRaw = $booking->getEvent()->getName();
        }
        $eventCode = strtoupper($this->sanitizeSegment($eventCodeRaw, 6, 'EV' . $booking->getEvent()->getId()));

        $dateCode = $booking->getEvent()->getDateTime()->format('Ymd');
        $ext = $isZip ? 'zip' : 'pdf';
        $suffix = $isZip ? 'tickets' : 'ticket';

        return sprintf('%s-%s-%s-b%d-%s.%s', $userCode, $dateCode, $eventCode, $booking->getId(), $suffix, $ext);
    }

    private function sanitizeSegment(string $value, int $maxLen, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            $value = strtolower($fallback);
        }

        if (strlen($value) > $maxLen) {
            $value = substr($value, 0, $maxLen);
            $value = rtrim($value, '-');
        }

        return $value === '' ? strtolower($fallback) : $value;
    }
}
