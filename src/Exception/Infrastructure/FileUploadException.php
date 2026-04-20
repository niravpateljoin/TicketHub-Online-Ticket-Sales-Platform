<?php

namespace App\Exception\Infrastructure;

use App\Exception\AppException;

class FileUploadException extends AppException
{
    public function __construct(string $message = 'File upload failed.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'FILE_UPLOAD_ERROR', 422, $previous);
    }
}
