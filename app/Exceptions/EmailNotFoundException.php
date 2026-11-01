<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;

final class EmailNotFoundException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Email not found',
            errorCode: ErrorCode::EMAIL_NOT_FOUND,
            statusCode: HttpStatus::UNPROCESSABLE_ENTITY,
            errors: [
                'email' => ['This email is not registered.'],
            ],
        );
    }
}
