<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * EVENT_EDIT  — only the event's own organizer may edit
 * EVENT_CANCEL — own organizer OR any admin
 */
class EventVoter extends Voter
{
    public const EVENT_EDIT   = 'EVENT_EDIT';
    public const EVENT_CANCEL = 'EVENT_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EVENT_EDIT, self::EVENT_CANCEL], true)
            && $subject instanceof Event;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Event $event */
        $event = $subject;

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::EVENT_EDIT   => $this->isOwnOrganizer($event, $user),
            self::EVENT_CANCEL => $this->isOwnOrganizer($event, $user) || $this->isAdmin($user),
            default            => false,
        };
    }

    private function isOwnOrganizer(Event $event, User $user): bool
    {
        return $event->getOrganizer()->getUser()->getId() === $user->getId();
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}
