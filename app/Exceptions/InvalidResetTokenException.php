<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;

final class InvalidResetTokenException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invalid or expired reset token',
            errorCode: ErrorCode::INVALID_RESET_TOKEN,
            statusCode: HttpStatus::UNPROCESSABLE_ENTITY,
            errors: [
                'token' => ['The reset token is invalid or expired.'],
            ],
        );
    }
}
