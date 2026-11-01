<?php

namespace Tests\Unit\Application\DTO\Auth;

use App\Application\DTO\Auth\ResetPasswordDTO;
use PHPUnit\Framework\TestCase;

class ResetPasswordDTOTest extends TestCase
{
    public function test_returns_validated_credentials_without_serializing_them(): void
    {
        $dto = new ResetPasswordDTO(
            email: 'user@example.com',
            token: 'reset-token',
            password: 'NewPassword123!',
        );

        $this->assertSame('user@example.com', $dto->getEmail());
        $this->assertSame('reset-token', $dto->getToken());
        $this->assertSame('NewPassword123!', $dto->getPassword());
        $this->assertSame('{}', json_encode($dto));
    }
}
