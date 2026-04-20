<?php

namespace App\Controller\Api\Auth;

use App\Controller\Api\ApiController;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class ResetPasswordController extends ApiController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $body = json_decode((string) $request->getContent(), true) ?? [];
        $token    = trim((string) ($body['token'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($token === '') {
            return $this->error('Reset token is required.', 422);
        }

        if (strlen($password) < 8) {
            return $this->error('Validation failed.', 422, ['password' => 'Password must be at least 8 characters.']);
        }

        $user = $this->userRepository->findOneBy(['passwordResetToken' => $token]);

        if ($user === null) {
            return $this->error('This reset link is invalid or has already been used.', 404);
        }

        if ($user->getPasswordResetTokenExpiresAt() < new \DateTime()) {
            return $this->error('This reset link has expired. Please request a new one.', 410);
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setPasswordResetToken(null);
        $user->setPasswordResetTokenExpiresAt(null);
        $this->em->flush();

        return $this->success([], message: 'Password reset successfully. You can now log in.');
    }
}
