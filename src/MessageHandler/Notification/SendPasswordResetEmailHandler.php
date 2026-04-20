<?php

namespace App\MessageHandler\Notification;

use App\Message\Notification\SendPasswordResetEmailMessage;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final class SendPasswordResetEmailHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendPasswordResetEmailMessage $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if ($user === null || $user->getPasswordResetToken() === null) {
            $this->logger->warning('SendPasswordResetEmailMessage: user #{id} not found or token already cleared.', [
                'id' => $message->userId,
            ]);
            return;
        }

        $resetUrl = $this->router->generate('app_react', [
            'reactRouting' => 'reset-password',
            'token' => $user->getPasswordResetToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('no-reply@tickethub.local')
            ->to($user->getEmail())
            ->subject('Reset your TicketHub password')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->textTemplate('emails/password_reset.txt.twig')
            ->context([
                'user'     => $user,
                'resetUrl' => $resetUrl,
            ]);

        $this->mailer->send($email);

        $this->logger->info('Password reset email sent to {email}.', ['email' => $user->getEmail()]);
    }
}
