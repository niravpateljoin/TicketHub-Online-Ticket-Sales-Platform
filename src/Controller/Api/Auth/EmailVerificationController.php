<?php

namespace App\Controller\Api\Auth;

use App\Controller\Api\ApiController;
use App\Service\AdministratorVerificationMailer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class EmailVerificationController extends ApiController
{
    public function __construct(
        private readonly AdministratorVerificationMailer $administratorVerificationMailer,
    ) {}

    #[Route('/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $token = trim((string) $request->query->get('token', ''));

        if ($token === '') {
            return $this->error('Verification token is required.', 422);
        }

        $verification = $this->administratorVerificationMailer->verifyToken($token);

        if ($verification === null) {
            return $this->error('This verification link is invalid or has already been used.', 404);
        }

        $user = $verification['user'];
        $isAdmin = $user->getRole() === 'ROLE_ADMIN';
        $message = $verification['mode'] === 'email_change'
            ? 'Administrator email updated.'
            : ($isAdmin ? 'Administrator email verified.' : 'Email verified. You can now log in.');

        return $this->success([
            'email' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
        ], message: $message);
    }
}
