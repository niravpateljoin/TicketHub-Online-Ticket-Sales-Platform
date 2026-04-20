<?php

namespace App\MessageHandler\Notification;

use App\Message\Notification\SendVerificationEmailMessage;
use App\Repository\UserRepository;
use App\Service\AdministratorVerificationMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendVerificationEmailMessageHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AdministratorVerificationMailer $verificationMailer,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendVerificationEmailMessage $message): void
    {
        $user = $this->userRepository->find($message->userId);

        if ($user === null) {
            $this->logger->warning('SendVerificationEmailMessage: user #{id} not found — skipping.', [
                'id' => $message->userId,
            ]);
            return;
        }

        $this->verificationMailer->sendVerificationEmail($user);
    }
}

