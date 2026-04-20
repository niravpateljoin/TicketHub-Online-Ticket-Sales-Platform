<?php

namespace App\Exception\Infrastructure;

use App\Exception\AppException;

class PdfGenerationException extends AppException
{
    public function __construct(string $message = 'PDF generation failed.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'PDF_GENERATION_ERROR', 500, $previous);
    }
}
