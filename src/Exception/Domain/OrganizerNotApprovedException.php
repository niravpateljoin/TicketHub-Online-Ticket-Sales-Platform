<?php

namespace App\Exception\Domain;

use App\Exception\AppException;

class OrganizerNotApprovedException extends AppException
{
    public function __construct()
    {
        parent::__construct(
            'Your organizer account is pending admin approval.',
            'ORGANIZER_NOT_APPROVED',
            403,
        );
    }
}
