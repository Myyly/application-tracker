<?php

namespace App\Application\DTO\Auth;

final readonly class ResetPasswordDTO
{
    public function __construct(
        private string $email,
        private string $token,
        private string $password,
    ) {}

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
