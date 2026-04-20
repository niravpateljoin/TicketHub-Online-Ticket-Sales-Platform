<?php

namespace App\Exception\Domain;

use App\Exception\AppException;

class EventCancelledException extends AppException
{
    public function __construct()
    {
        parent::__construct('This event has been cancelled.', 'EVENT_CANCELLED', 409);
    }
}
