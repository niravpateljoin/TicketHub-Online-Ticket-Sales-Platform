<?php

namespace App\MessageHandler\Notification;

use App\Message\Notification\OrganizerRejectedMessage;
use App\Repository\OrganizerRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final class OrganizerRejectedMessageHandler
{
    public function __construct(
        private readonly OrganizerRepository $organizerRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(OrganizerRejectedMessage $message): void
    {
        $organizer = $this->organizerRepository->find($message->organizerId);
        if ($organizer === null) {
            $this->logger->warning('OrganizerRejectedMessage: organizer #{id} not found — skipping.', [
                'id' => $message->organizerId,
            ]);
            return;
        }

        $user = $organizer->getUser();
        $html = $this->twig->render('emails/organizer_rejected.html.twig', [
            'name' => $user->getName() ?: $user->getEmail(),
            'email' => $user->getEmail(),
        ]);

        $email = (new Email())
            ->from('no-reply@tickethub.local')
            ->to($message->email)
            ->subject('Your organizer account request was rejected')
            ->html($html);

        $this->mailer->send($email);

        $this->logger->info('OrganizerRejectedMessage: rejection email sent for organizer #{id} to {email}.', [
            'id' => $message->organizerId,
            'email' => $message->email,
        ]);
    }
}

