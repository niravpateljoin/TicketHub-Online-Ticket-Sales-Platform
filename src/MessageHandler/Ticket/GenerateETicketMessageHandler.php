<?php

namespace App\MessageHandler\Ticket;

use App\Message\Ticket\GenerateETicketMessage;
use App\Repository\ETicketRepository;
use App\Service\ETicketGeneratorService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consumes GenerateETicketMessage from ticket_queue.
 * Generates the PDF e-ticket and persists the file path.
 */
#[AsMessageHandler]
final class GenerateETicketMessageHandler
{
    public function __construct(
        private readonly ETicketRepository $eTicketRepository,
        private readonly ETicketGeneratorService $eTicketGenerator,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateETicketMessage $message): void
    {
        $eTicket = $this->eTicketRepository->find($message->eTicketId);

        if ($eTicket === null) {
            $this->logger->warning('GenerateETicketMessage: eTicket #{id} not found — skipping.', [
                'id' => $message->eTicketId,
            ]);
            return;
        }

        if ($eTicket->isPdfReady()) {
            $this->logger->info('GenerateETicketMessage: eTicket #{id} already has a PDF — skipping.', [
                'id' => $message->eTicketId,
            ]);
            return;
        }

        $this->eTicketGenerator->generate($eTicket);

        $this->logger->info('GenerateETicketMessage: PDF generated for eTicket #{id} (booking #{bookingId}).', [
            'id'        => $message->eTicketId,
            'bookingId' => $message->bookingId,
        ]);
    }
}
