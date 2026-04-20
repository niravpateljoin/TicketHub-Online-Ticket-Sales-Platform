<?php

namespace App\MessageHandler\Notification;

use App\Entity\User;
use App\Message\Notification\EventCancelledMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Consumes EventCancelledMessage from notification_queue.
 * Sends a cancellation + refund notification email to every affected user.
 */
#[AsMessageHandler]
final class EventCancelledMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EventCancelledMessage $message): void
    {
        $sentCount = 0;

        foreach ($message->refundMap as $userId => $creditsRefunded) {
            $user = $this->em->find(User::class, $userId);

            if (!$user instanceof User) {
                $this->logger->warning('EventCancelledMessage: user #{id} not found — skipping.', [
                    'id' => $userId,
                ]);
                continue;
            }

            $html = $this->twig->render('emails/event_cancelled.html.twig', [
                'eventId'         => $message->eventId,
                'eventName'       => $message->eventName,
                'userName'        => $user->getName() ?: $user->getEmail(),
                'creditsRefunded' => $creditsRefunded,
            ]);

            $email = (new Email())
                ->from('no-reply@tickethub.local')
                ->to($user->getEmail())
                ->subject(sprintf('Event Cancelled — %s', $message->eventName))
                ->html($html);

            $this->mailer->send($email);
            ++$sentCount;
        }

        $this->logger->info('EventCancelledMessage: sent {count} cancellation email(s) for event #{eventId}.', [
            'count'   => $sentCount,
            'eventId' => $message->eventId,
        ]);
    }
}
