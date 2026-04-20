<?php

namespace App\Message\Notification;

final class WaitlistNotificationMessage
{
    public function __construct(
        public readonly int $waitlistId,
    ) {}
}
