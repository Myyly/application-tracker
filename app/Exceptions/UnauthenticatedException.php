<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;

class UnauthenticatedException extends ApiException
{
    public function __construct(string $message = 'Unauthenticated')
    {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::UNAUTHORIZED,
            statusCode: HttpStatus::UNAUTHORIZED,
        );
    }
}
