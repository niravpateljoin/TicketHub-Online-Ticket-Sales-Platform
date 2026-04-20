<?php

namespace App\Exception\Domain;

use App\Exception\AppException;

class DuplicateBookingException extends AppException
{
    public int $existingBookingId;

    public function __construct(int $existingBookingId)
    {
        parent::__construct('This checkout was already processed.', 'DUPLICATE_BOOKING', 409);
        $this->existingBookingId = $existingBookingId;
    }
}
