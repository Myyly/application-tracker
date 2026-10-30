<?php

namespace App\Infrastructure\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

final class RegisteredUserDispatcher
{
    public function dispatch(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        event(new Registered($user));
    }
}
