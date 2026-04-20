<?php

namespace App\Security\Voter;

use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * BOOKING_VIEW     — only the booking owner
 * TICKET_DOWNLOAD  — only the booking owner (and booking must be confirmed)
 */
class BookingVoter extends Voter
{
    public const BOOKING_VIEW    = 'BOOKING_VIEW';
    public const TICKET_DOWNLOAD = 'TICKET_DOWNLOAD';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::BOOKING_VIEW, self::TICKET_DOWNLOAD], true)
            && $subject instanceof Booking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Booking $booking */
        $booking = $subject;

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isOwner = $booking->getUser()->getId() === $user->getId();

        return match ($attribute) {
            self::BOOKING_VIEW    => $isOwner,
            self::TICKET_DOWNLOAD => $isOwner && $booking->getStatus() === 'confirmed',
            default               => false,
        };
    }
}
