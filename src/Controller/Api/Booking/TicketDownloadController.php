<?php

namespace App\Controller\Api\Booking;

use App\Controller\Api\ApiController;
use App\Entity\Booking;
use App\Entity\ETicket;
use App\Repository\BookingRepository;
use App\Repository\ETicketRepository;
use App\Security\Voter\BookingVoter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class TicketDownloadController extends ApiController
{
    public function __construct(
        private readonly ETicketRepository $eTicketRepository,
        private readonly BookingRepository $bookingRepository,
    ) {}

    #[Route('/api/tickets/{qrToken}/download', name: 'api_tickets_download', methods: ['GET'])]
    public function downloadByQrToken(string $qrToken): BinaryFileResponse|JsonResponse
    {
        $eTicket = $this->eTicketRepository->findOneBy(['qrToken' => $qrToken]);

        if (!$eTicket instanceof ETicket) {
            return $this->error('Ticket not found.', 404);
        }

        $booking = $eTicket->getBookingItem()->getBooking();
        // This endpoint is token-based (qrToken in URL) so email links can be downloaded
        // without requiring JWT in browser headers.

        $path = $eTicket->getFilePath();
        if ($path === null || $path === '' || !is_file($path)) {
            return new JsonResponse(['message' => 'Ticket is being generated. Try again shortly.'], 202);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->buildTicketFileName($booking, false)
        );

        return $response;
    }

    #[Route('/api/bookings/{id}/ticket', name: 'api_bookings_download_legacy', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function downloadByBooking(int $id): BinaryFileResponse|JsonResponse
    {
        $booking = $this->bookingRepository->find($id);

        if (!$booking instanceof Booking) {
            return $this->error('Booking not found.', 404);
        }

        $this->denyAccessUnlessGranted(BookingVoter::TICKET_DOWNLOAD, $booking);

        foreach ($booking->getBookingItems() as $item) {
            if ($item->getETicket() instanceof ETicket) {
                $path = $item->getETicket()->getFilePath();
                if ($path === null || $path === '' || !is_file($path)) {
                    break;
                }

                $response = new BinaryFileResponse($path);
                $response->headers->set('Content-Type', 'application/pdf');
                $response->setContentDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $this->buildTicketFileName($booking, false)
                );

                return $response;
            }
        }

        return new JsonResponse(['message' => 'Ticket is being generated. Try again shortly.'], 202);
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
