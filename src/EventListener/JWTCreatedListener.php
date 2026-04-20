<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\OrganizerRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

/**
 * Enriches the JWT payload with custom claims so the React frontend
 * can read user info (id, role, creditBalance, approvalStatus) without
 * making a separate /api/auth/me request on every page load.
 */
class JWTCreatedListener
{
    public function __construct(private readonly OrganizerRepository $organizerRepository) {}

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();

        $payload['id']            = $user->getId();
        $payload['name']          = $user->getName();
        $payload['email']         = $user->getEmail();
        $payload['pendingEmail']  = $user->getPendingEmail();
        $payload['roles']         = $user->getRoles();
        $payload['creditBalance'] = $user->getCreditBalance();
        $payload['isVerified']    = $user->isVerified();

        // Include organizer approval status when applicable
        $approvalStatus = null;
        if (in_array('ROLE_ORGANIZER', $user->getRoles(), true)) {
            $organizer = $this->organizerRepository->findOneBy(['user' => $user]);
            $approvalStatus = $organizer?->getApprovalStatus();
        }
        $payload['approvalStatus'] = $approvalStatus;

        $event->setData($payload);
    }
}
