<?php

namespace App\Infrastructure\Auth;

use App\Exceptions\InvalidResetTokenException;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use LogicException;
use RuntimeException;

final class PasswordResetter
{
    public function reset(string $email, string $token, string $password): void
    {
        $resetUser = null;

        // The outer transaction also covers the broker's token deletion after its callback returns.
        $status = DB::transaction(function () use ($email, $token, $password, &$resetUser): string {
            // Serialize resets for one stored token on transactional databases such as MySQL.
            DB::table((string) config('auth.passwords.users.table'))
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            return Password::reset([
                'email' => $email,
                'token' => $token,
                'password' => $password,
            ], function (CanResetPassword $resettableUser, string $plainPassword) use (&$resetUser): void {
                if (! $resettableUser instanceof User) {
                    throw new LogicException('The configured password broker must return an application user.');
                }

                // Assign plaintext once; the User model's hashed cast applies bcrypt without double hashing.
                $resettableUser->password = $plainPassword;

                if (! $resettableUser->save()) {
                    throw new RuntimeException('Failed to persist the new password.');
                }

                // Password changes do not invalidate Sanctum bearer tokens, so revoke every device explicitly.
                $resettableUser->tokens()->delete();
                $resetUser = $resettableUser;
            });
        });

        match ($status) {
            Password::PASSWORD_RESET => null,
            Password::INVALID_TOKEN,
            Password::INVALID_USER => throw new InvalidResetTokenException,
            default => throw new RuntimeException('Failed to reset the password.'),
        };

        if (! $resetUser instanceof User) {
            throw new LogicException('A successful password reset must identify its user.');
        }

        // Dispatch only after commit so listeners cannot observe uncommitted password or token state.
        event(new PasswordReset($resetUser));
    }
}
