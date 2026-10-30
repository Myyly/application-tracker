<?php

namespace Tests\Unit\Application\DTO\Auth;

use App\Application\DTO\Auth\LoginResultDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LoginResultDTOTest extends TestCase
{
    public function test_returns_token_data_with_utc_expiration(): void
    {
        $expiresAt = new DateTimeImmutable('2026-11-06 19:00:00+07:00');
        $dto = new LoginResultDTO('plain-text-token', $expiresAt);

        $this->assertSame('plain-text-token', $dto->getAccessToken());
        $this->assertSame($expiresAt, $dto->getExpiresAt());
        $this->assertSame([
            'accessToken' => 'plain-text-token',
            'tokenType' => 'Bearer',
            'expiresAt' => '2026-11-06T12:00:00Z',
        ], $dto->toArray());
    }
}
