<?php

namespace App\Infrastructure\Auth;

use App\Exceptions\EmailNotFoundException;
use Illuminate\Support\Facades\Password;
use RuntimeException;

final class PasswordResetLinkSender
{
    public function send(string $email): void
    {
        // The broker creates and hashes a reset token, then sends Laravel's notification synchronously.
        // Its reset token remains independent of Sanctum access tokens.
        $status = Password::sendResetLink(['email' => $email]);

        // A broker cooldown returns generic success without creating or sending another reset token.
        match ($status) {
            Password::RESET_LINK_SENT,
            Password::RESET_THROTTLED => null,
            // This approved 422 mapping intentionally exposes account existence to the client.
            Password::INVALID_USER => throw new EmailNotFoundException,
            default => throw new RuntimeException('Failed to send a password reset link.'),
        };
    }
}
