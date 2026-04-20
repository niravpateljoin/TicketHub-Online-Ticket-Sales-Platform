<?php

namespace App\Exception;

final class CartException extends AppException
{
    public function __construct(string $message, int $statusCode = 409)
    {
        parent::__construct($message, 'CART_ERROR', $statusCode);
    }
}
