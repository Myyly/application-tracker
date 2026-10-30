<?php

namespace App\Application\Services;

use App\Infrastructure\Auth\EmailVerificationVerifier;

final class AuthService
{
    public function __construct(
        private readonly EmailVerificationVerifier $emailVerificationVerifier,
    ) {}

    public function verifyEmail(int $id, string $hash): void
    {
        $this->emailVerificationVerifier->verify($id, $hash);
    }
}
