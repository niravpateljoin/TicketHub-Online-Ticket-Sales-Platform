<?php

namespace App\Message\Notification;

final class OrganizerRejectedMessage
{
    public function __construct(
        public readonly int $organizerId,
        public readonly string $email,
    ) {}
}

