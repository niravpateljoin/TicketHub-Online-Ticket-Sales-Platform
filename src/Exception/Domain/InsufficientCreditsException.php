<?php

namespace App\Exception\Domain;

use App\Exception\AppException;

class InsufficientCreditsException extends AppException
{
    public function __construct(int $required, int $available)
    {
        parent::__construct(
            "Insufficient credits. Required: {$required}, available: {$available}.",
            'INSUFFICIENT_CREDITS',
            422,
        );
    }
}
