<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\OrganizerRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

/**
 * Appends `user` object to the login success response so the React
 * frontend gets the user profile in the same request as the token,
 * without needing a follow-up /api/auth/me call after login.
 */
class AuthenticationSuccessListener
{
    public function __construct(private readonly OrganizerRepository $organizerRepository) {}

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $approvalStatus = null;
        if (in_array('ROLE_ORGANIZER', $user->getRoles(), true)) {
            $organizer = $this->organizerRepository->findOneBy(['user' => $user]);
            $approvalStatus = $organizer?->getApprovalStatus();
        }

        $data = $event->getData();
        $data['user'] = [
            'id'             => $user->getId(),
            'name'           => $user->getName(),
            'email'          => $user->getEmail(),
            'pendingEmail'   => $user->getPendingEmail(),
            'roles'          => $user->getRoles(),
            'creditBalance'  => $user->getCreditBalance(),
            'isVerified'     => $user->isVerified(),
            'approvalStatus' => $approvalStatus,
        ];

        $event->setData($data);
    }
}
