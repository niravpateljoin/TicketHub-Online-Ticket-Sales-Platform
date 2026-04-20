<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getRole() === 'ROLE_ADMIN' && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Your administrator account is pending email verification.');
        }

        if ($user->getRole() === 'ROLE_USER' && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Your account is pending email verification. Please check your inbox.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
