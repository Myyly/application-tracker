<?php

namespace App\Infrastructure\Auth;

use Laravel\Sanctum\PersonalAccessToken;

final class CurrentAccessTokenRevoker
{
    public function revoke(int $currentAccessTokenId): void
    {
        // Delete only the authenticated bearer token ID; other device tokens remain valid until revoked or expired.
        PersonalAccessToken::query()
            ->whereKey($currentAccessTokenId)
            ->delete();
    }
}
