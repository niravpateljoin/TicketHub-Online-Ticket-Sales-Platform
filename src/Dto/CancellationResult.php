<?php

namespace App\Dto;

final readonly class CancellationResult
{
    public function __construct(
        public int $usersRefunded,
        public int $creditsRefunded,
    ) {}
}
