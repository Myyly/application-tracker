<?php

namespace Tests\Unit\Application\DTO\Auth;

use App\Application\DTO\Auth\RegisterDTO;
use PHPUnit\Framework\TestCase;

class RegisterDTOTest extends TestCase
{
    public function test_returns_constructor_values(): void
    {
        $dto = new RegisterDTO(
            name: 'My Ly',
            email: 'myly@example.com',
            password: 'secret-password',
        );

        $this->assertSame('My Ly', $dto->getName());
        $this->assertSame('myly@example.com', $dto->getEmail());
        $this->assertSame('secret-password', $dto->getPassword());
    }

    public function test_does_not_serialize_credentials(): void
    {
        $dto = new RegisterDTO(
            name: 'My Ly',
            email: 'myly@example.com',
            password: 'secret-password',
        );

        $this->assertSame('{}', json_encode($dto));
    }
}
