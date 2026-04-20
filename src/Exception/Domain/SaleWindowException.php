<?php

namespace App\Exception\Domain;

use App\Exception\AppException;

class SaleWindowException extends AppException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason, 'SALE_WINDOW_CLOSED', 409);
    }
}
