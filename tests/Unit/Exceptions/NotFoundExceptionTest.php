<?php

namespace Tests\Unit\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;
use App\Exceptions\ApiException;
use App\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;

class NotFoundExceptionTest extends TestCase
{
    public function test_returns_the_default_not_found_error(): void
    {
        $exception = new NotFoundException;

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame('Not found', $exception->getMessage());
        $this->assertSame(ErrorCode::NOT_FOUND, $exception->getErrorCode());
        $this->assertSame(HttpStatus::NOT_FOUND, $exception->getStatusCode());
        $this->assertSame([], $exception->getErrors());
    }

    public function test_preserves_a_custom_message(): void
    {
        $exception = new NotFoundException('Application not found.');

        $this->assertSame('Application not found.', $exception->getMessage());
    }
}
