<?php

namespace App\Message\Notification;

final class OrganizerApprovedMessage
{
    public function __construct(
        public readonly int $organizerId,
        public readonly string $email,
    ) {}
}

