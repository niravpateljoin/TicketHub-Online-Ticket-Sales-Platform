<?php

namespace App\MessageHandler\Notification;

use App\Message\Notification\BookingConfirmedMessage;
use App\Repository\BookingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Consumes BookingConfirmedMessage from notification_queue.
 * Sends a booking confirmation email with ticket download links.
 */
#[AsMessageHandler]
final class BookingConfirmedMessageHandler
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(BookingConfirmedMessage $message): void
    {
        $booking = $this->bookingRepository->find($message->bookingId);

        if ($booking === null) {
            $this->logger->warning('BookingConfirmedMessage: booking #{id} not found — skipping.', [
                'id' => $message->bookingId,
            ]);
            return;
        }

        $html = $this->twig->render('emails/booking_confirmed.html.twig', [
            'booking' => $booking,
        ]);

        $email = (new Email())
            ->from('no-reply@tickethub.local')
            ->to($message->userEmail)
            ->subject(sprintf('Booking Confirmed — %s', $booking->getEvent()->getName()))
            ->html($html);

        $this->mailer->send($email);

        $this->logger->info('BookingConfirmedMessage: confirmation email sent for booking #{id} to {email}.', [
            'id'    => $message->bookingId,
            'email' => $message->userEmail,
        ]);
    }
}
