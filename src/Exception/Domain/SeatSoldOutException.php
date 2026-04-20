<?php

namespace App\Exception\Domain;

use App\Exception\AppException;

class SeatSoldOutException extends AppException
{
    public function __construct(string $tierName)
    {
        parent::__construct(
            "Sorry, \"{$tierName}\" just sold out.",
            'SEAT_SOLD_OUT',
            409,
        );
    }
}
