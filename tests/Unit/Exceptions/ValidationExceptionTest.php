<?php

namespace Tests\Unit\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;
use App\Exceptions\ApiException;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function test_returns_the_default_validation_error(): void
    {
        $exception = new ValidationException;

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame(ErrorCode::VALIDATION_ERROR, $exception->getErrorCode());
        $this->assertSame(HttpStatus::UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame([], $exception->getErrors());
    }

    public function test_preserves_validation_errors(): void
    {
        $errors = ['email' => ['The email field is required.']];

        $exception = new ValidationException(errors: $errors);

        $this->assertSame($errors, $exception->getErrors());
    }

    public function test_preserves_a_custom_message(): void
    {
        $exception = new ValidationException('The submitted data is invalid.');

        $this->assertSame('The submitted data is invalid.', $exception->getMessage());
    }
}
