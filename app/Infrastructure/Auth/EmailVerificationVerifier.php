<?php

namespace App\Infrastructure\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EmailVerificationVerifier
{
    public function verify(int $id, string $hash): void
    {
        $verifiedUser = DB::transaction(function () use ($id, $hash): ?User {
            $user = User::query()->lockForUpdate()->findOrFail($id);

            if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
                throw new AuthorizationException;
            }

            if ($user->hasVerifiedEmail()) {
                return null;
            }

            if (! $user->markEmailAsVerified()) {
                throw new RuntimeException('Failed to persist email verification.');
            }

            return $user;
        });

        if ($verifiedUser !== null) {
            event(new Verified($verifiedUser));
        }
    }
}
