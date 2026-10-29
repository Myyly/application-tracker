<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;

class ValidationException extends ApiException
{
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
    ) {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::VALIDATION_ERROR,
            statusCode: HttpStatus::UNPROCESSABLE_ENTITY,
            errors: $errors,
        );
    }
}
