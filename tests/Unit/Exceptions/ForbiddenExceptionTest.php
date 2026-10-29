<?php

namespace Tests\Unit\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;
use App\Exceptions\ApiException;
use App\Exceptions\ForbiddenException;
use PHPUnit\Framework\TestCase;

class ForbiddenExceptionTest extends TestCase
{
    public function test_returns_the_default_forbidden_error(): void
    {
        $exception = new ForbiddenException;

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame('Forbidden', $exception->getMessage());
        $this->assertSame(ErrorCode::FORBIDDEN, $exception->getErrorCode());
        $this->assertSame(HttpStatus::FORBIDDEN, $exception->getStatusCode());
        $this->assertSame([], $exception->getErrors());
    }

    public function test_preserves_a_custom_message(): void
    {
        $exception = new ForbiddenException('You cannot access this CV.');

        $this->assertSame('You cannot access this CV.', $exception->getMessage());
    }
}
