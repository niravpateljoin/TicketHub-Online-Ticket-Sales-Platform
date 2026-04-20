<?php

namespace App\Exception\Auth;

use App\Exception\AppException;

class AccountDeactivatedException extends AppException
{
    public function __construct()
    {
        parent::__construct('Your account has been deactivated.', 'ACCOUNT_DEACTIVATED', 403);
    }
}
