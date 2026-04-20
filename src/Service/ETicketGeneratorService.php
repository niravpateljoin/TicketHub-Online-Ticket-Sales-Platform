<?php

namespace App\Service;

use App\Entity\ETicket;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Generates a PDF e-ticket for a single ETicket entity.
 *
 * Flow:
 *  1. Build a QR code PNG (data URI) from the qrToken.
 *  2. Render the Twig template to HTML.
 *  3. Convert HTML → PDF via Dompdf.
 *  4. Persist the file to var/tickets/ (outside the web root).
 *  5. Update ETicket.filePath + generatedAt and flush.
 *
 * Called synchronously from CheckoutService for now.
 * TASK-10 will dispatch a Messenger message instead so this runs in a worker.
 */
class ETicketGeneratorService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * Generates the PDF for the given ETicket and persists the file path.
     *
     * @throws \RuntimeException if the tickets directory cannot be written to
     */
    public function generate(ETicket $eTicket): void
    {
        $ticketsDir = $this->projectDir . '/var/tickets';
        if (!is_dir($ticketsDir) && !mkdir($ticketsDir, 0755, true) && !is_dir($ticketsDir)) {
            throw new \RuntimeException(sprintf('Cannot create tickets directory: %s', $ticketsDir));
        }

        $bookingItem = $eTicket->getBookingItem();
        $booking     = $bookingItem->getBooking();
        $tier        = $bookingItem->getTicketTier();
        $event       = $tier->getEvent();
        $user        = $booking->getUser();

        // ── 1. Generate QR code as base64 data URI ────────────────────────────────────
        $qrCode = new QrCode(data: $eTicket->getQrToken(), size: 200, margin: 10);

        $writer    = new PngWriter();
        $qrResult  = $writer->write($qrCode);
        $qrDataUri = $qrResult->getDataUri();

        // ── 2. Render HTML via Twig ───────────────────────────────────────────────────
        $html = $this->twig->render('eticket/ticket.html.twig', [
            'event'       => $event,
            'tier'        => $tier,
            'booking'     => $booking,
            'bookingItem' => $bookingItem,
            'eTicket'     => $eTicket,
            'user'        => $user,
            'qrDataUri'   => $qrDataUri,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        // ── 3. Convert HTML → PDF ─────────────────────────────────────────────────────
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // ── 4. Save to disk ───────────────────────────────────────────────────────────
        $fileName = sprintf('%d-%s.pdf', $booking->getId(), $eTicket->getQrToken());
        $filePath = $ticketsDir . '/' . $fileName;

        file_put_contents($filePath, $dompdf->output());

        // ── 5. Persist filePath + generatedAt ─────────────────────────────────────────
        $eTicket->setFilePath($filePath);
        $eTicket->setGeneratedAt(new \DateTime());
        $this->em->flush();
    }
}
