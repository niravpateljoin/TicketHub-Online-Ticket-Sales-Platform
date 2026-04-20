<?php

namespace App\Controller\Api\Auth;

use App\Controller\Api\ApiController;
use App\Message\Notification\SendPasswordResetEmailMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class ForgotPasswordController extends ApiController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true) ?? [];
        $email = trim((string) ($body['email'] ?? ''));

        if ($email === '') {
            return $this->error('Email is required.', 422);
        }

        // Always return success to prevent email enumeration
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user !== null) {
            $user->setPasswordResetToken(bin2hex(random_bytes(32)));
            $user->setPasswordResetTokenExpiresAt(new \DateTime('+1 hour'));
            $this->em->flush();

            $this->bus->dispatch(new SendPasswordResetEmailMessage((int) $user->getId()));
        }

        return $this->success(
            [],
            200,
            'If an account with that email exists, a password reset link has been sent.'
        );
    }
}
