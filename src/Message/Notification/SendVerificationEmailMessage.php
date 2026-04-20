<?php

namespace App\Message\Notification;

final class SendVerificationEmailMessage
{
    public function __construct(
        public readonly int $userId,
    ) {}
}

