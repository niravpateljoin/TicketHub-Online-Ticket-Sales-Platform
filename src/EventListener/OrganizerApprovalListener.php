<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\OrganizerRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Guards /api/organizer/** routes: only organizers with approvalStatus = 'approved'
 * can proceed. Returns 403 with a clear message for pending/rejected accounts.
 *
 * Runs at kernel.request priority -10 — after the JWT authenticator (priority -8)
 * has already validated the token and set the user on the token storage.
 */
class OrganizerApprovalListener
{
    public function __construct(
        private readonly OrganizerRepository  $organizerRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only applies to /api/organizer routes
        if (!str_starts_with($request->getPathInfo(), '/api/organizer')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return; // Not authenticated yet — JWT firewall / access_control handles 401
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!in_array('ROLE_ORGANIZER', $user->getRoles(), true)) {
            return;
        }

        $organizer = $this->organizerRepository->findOneBy(['user' => $user]);

        if ($organizer === null || !$organizer->isApproved() || $organizer->getDeactivatedAt() !== null) {
            $message = match ($organizer?->getApprovalStatus()) {
                null       => 'Organizer profile not found.',
                'rejected' => 'Your organizer account has been rejected.',
                'approved' => 'Your organizer account has been deactivated.',
                default    => 'Your account is pending admin approval.',
            };

            $event->setResponse(new JsonResponse(['message' => $message], 403));
        }
    }
}
