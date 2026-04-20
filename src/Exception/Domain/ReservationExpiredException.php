<?php

namespace App\Exception\Domain;

use App\Exception\AppException;

class ReservationExpiredException extends AppException
{
    public function __construct()
    {
        parent::__construct(
            'Your reservation has expired. Please add tickets to your cart again.',
            'RESERVATION_EXPIRED',
            409,
        );
    }
}
