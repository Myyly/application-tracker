<?php

namespace Tests\Unit\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;
use App\Exceptions\ApiException;
use App\Exceptions\UnauthenticatedException;
use PHPUnit\Framework\TestCase;

class UnauthenticatedExceptionTest extends TestCase
{
    public function test_returns_the_default_unauthenticated_error(): void
    {
        $exception = new UnauthenticatedException;

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame('Unauthenticated', $exception->getMessage());
        $this->assertSame(ErrorCode::UNAUTHORIZED, $exception->getErrorCode());
        $this->assertSame(HttpStatus::UNAUTHORIZED, $exception->getStatusCode());
        $this->assertSame([], $exception->getErrors());
    }

    public function test_preserves_a_custom_message(): void
    {
        $exception = new UnauthenticatedException('Authentication is required.');

        $this->assertSame('Authentication is required.', $exception->getMessage());
    }
}
