<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;

class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Not found')
    {
        parent::__construct(
            message: $message,
            errorCode: ErrorCode::NOT_FOUND,
            statusCode: HttpStatus::NOT_FOUND,
        );
    }
}
