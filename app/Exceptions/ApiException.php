<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;
use Exception;
use InvalidArgumentException;
use Throwable;

class ApiException extends Exception
{
    public function __construct(
        string $message = 'Internal server error',
        private readonly string $errorCode = ErrorCode::INTERNAL_SERVER_ERROR,
        private readonly int $statusCode = HttpStatus::INTERNAL_SERVER_ERROR,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        if ($statusCode < 400 || $statusCode > 599) {
            throw new InvalidArgumentException(
                "HTTP status code must be between 400 and 599; {$statusCode} given.",
            );
        }

        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
