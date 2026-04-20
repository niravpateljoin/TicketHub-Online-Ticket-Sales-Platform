<?php

namespace App\Message\Notification;

final class SendPasswordResetEmailMessage
{
    public function __construct(
        public readonly int $userId,
    ) {}
}
