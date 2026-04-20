<?php

namespace App\Exception\Validation;

use App\Exception\AppException;

class RequestValidationException extends AppException
{
    public function __construct(private readonly array $fieldErrors)
    {
        parent::__construct('Validation failed.', 'VALIDATION_ERROR', 422);
    }

    public function getFieldErrors(): array { return $this->fieldErrors; }
}
