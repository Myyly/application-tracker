<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;

final class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invalid email or password.',
            errorCode: ErrorCode::INVALID_CREDENTIALS,
            statusCode: HttpStatus::UNAUTHORIZED,
        );
    }
}
