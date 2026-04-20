<?php

namespace App\Exception\Auth;

use App\Exception\AppException;

class InvalidCredentialsException extends AppException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.', 'INVALID_CREDENTIALS', 401);
    }
}
