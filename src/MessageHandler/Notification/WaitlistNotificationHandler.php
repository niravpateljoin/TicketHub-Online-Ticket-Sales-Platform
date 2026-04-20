<?php

namespace App\MessageHandler\Notification;

use App\Entity\Waitlist;
use App\Message\Notification\WaitlistNotificationMessage;
use App\Repository\WaitlistRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class WaitlistNotificationHandler
{
    public function __construct(
        private readonly WaitlistRepository $waitlistRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(WaitlistNotificationMessage $message): void
    {
        $entry = $this->waitlistRepository->find($message->waitlistId);

        if ($entry === null || $entry->getStatus() !== Waitlist::STATUS_PENDING) {
            return;
        }

        $eventUrl = $this->router->generate('app_react', [
            'reactRouting' => 'events/' . $entry->getEvent()->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('no-reply@tickethub.local')
            ->to($entry->getUser()->getEmail())
            ->subject(sprintf('Seats available — %s', $entry->getEvent()->getName()))
            ->htmlTemplate('emails/waitlist_notification.html.twig')
            ->textTemplate('emails/waitlist_notification.txt.twig')
            ->context([
                'user'     => $entry->getUser(),
                'event'    => $entry->getEvent(),
                'tier'     => $entry->getTicketTier(),
                'eventUrl' => $eventUrl,
            ]);

        $this->mailer->send($email);

        $this->logger->info('Waitlist notification sent to {email} for event {event}.', [
            'email' => $entry->getUser()->getEmail(),
            'event' => $entry->getEvent()->getName(),
        ]);
    }
}
