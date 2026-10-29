<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::FORBIDDEN,
            statusCode: HttpStatus::FORBIDDEN,
        );
    }
}
