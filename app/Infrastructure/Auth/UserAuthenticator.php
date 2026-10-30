<?php

namespace App\Infrastructure\Auth;

use App\Application\DTO\Auth\LoginDTO;
use App\Application\DTO\Auth\LoginResultDTO;
use App\Application\Entity\UserEntity;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class UserAuthenticator
{
    private const string TOKEN_NAME = 'auth-token';

    public function authenticate(LoginDTO $dto): LoginResultDTO
    {
        $user = User::query()
            ->where(UserEntity::EMAIL, $dto->getEmail())
            ->first();

        // Authentication proves credentials only; email verification is a separate authorization concern.
        if ($user === null || ! Hash::check($dto->getPassword(), $user->getAuthPassword())) {
            throw new InvalidCredentialsException;
        }

        $expiresAt = now()->addDays(7);

        // Store the seven-day expires_at value so Sanctum rejects this bearer token after that timestamp.
        // Existing tokens are intentionally preserved, giving every device its own independently valid token.
        // Sanctum stores only the token hash; the plaintext value is returned once in this login response.
        $token = $user->createToken(self::TOKEN_NAME, ['*'], $expiresAt);

        return new LoginResultDTO(
            accessToken: $token->plainTextToken,
            expiresAt: $expiresAt->toDateTimeImmutable(),
        );
    }
}
