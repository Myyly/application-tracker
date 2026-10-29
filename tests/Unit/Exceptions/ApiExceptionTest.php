<?php

namespace Tests\Unit\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;
use App\Exceptions\ApiException;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ApiExceptionTest extends TestCase
{
    public function test_returns_default_values(): void
    {
        $exception = new ApiException;

        $this->assertSame('Internal server error', $exception->getMessage());
        $this->assertSame(ErrorCode::INTERNAL_SERVER_ERROR, $exception->getErrorCode());
        $this->assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        $this->assertSame([], $exception->getErrors());
        $this->assertSame(HttpStatus::INTERNAL_SERVER_ERROR, $exception->getCode());
    }

    public function test_preserves_custom_values(): void
    {
        $errors = ['application' => ['The application status is invalid.']];

        $exception = new ApiException(
            message: 'Application status conflict.',
            errorCode: ErrorCode::APPLICATION_STATUS_CONFLICT,
            statusCode: HttpStatus::CONFLICT,
            errors: $errors,
        );

        $this->assertSame('Application status conflict.', $exception->getMessage());
        $this->assertSame(ErrorCode::APPLICATION_STATUS_CONFLICT, $exception->getErrorCode());
        $this->assertSame(HttpStatus::CONFLICT, $exception->getStatusCode());
        $this->assertSame($errors, $exception->getErrors());
    }

    public function test_accepts_400_as_the_lower_status_code_boundary(): void
    {
        $exception = new ApiException(statusCode: 400);

        $this->assertSame(400, $exception->getStatusCode());
    }

    public function test_accepts_599_as_the_upper_status_code_boundary(): void
    {
        $exception = new ApiException(statusCode: 599);

        $this->assertSame(599, $exception->getStatusCode());
    }

    public function test_rejects_399_as_an_invalid_status_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApiException(statusCode: 399);
    }

    public function test_rejects_600_as_an_invalid_status_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApiException(statusCode: 600);
    }

    public function test_preserves_the_previous_exception(): void
    {
        $previous = new RuntimeException('Root cause.');

        $exception = new ApiException(previous: $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_is_an_exception(): void
    {
        $exception = new ApiException;

        $this->assertInstanceOf(Exception::class, $exception);
    }
}
