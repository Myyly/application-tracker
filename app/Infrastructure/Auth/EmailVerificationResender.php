<?php

namespace App\Infrastructure\Auth;

use App\Models\User;

final class EmailVerificationResender
{
    public function resend(int $userId): bool
    {
        $user = User::query()->findOrFail($userId);

        // A verified account needs no repeated notification or verification side effect.
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        // MustVerifyEmail builds a temporary signed URL for the existing verification.verify callback.
        $user->sendEmailVerificationNotification();

        return true;
    }
}
