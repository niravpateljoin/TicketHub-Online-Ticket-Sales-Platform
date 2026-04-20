<?php

namespace App\Exception\Infrastructure;

use App\Exception\AppException;

class QrCodeGenerationException extends AppException
{
    public function __construct(string $message = 'QR code generation failed.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'QR_CODE_GENERATION_ERROR', 500, $previous);
    }
}
