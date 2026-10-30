<?php

namespace App\Application\DTO\Auth;

use DateTimeImmutable;
use DateTimeZone;

final class LoginResultDTO
{
    private const string TOKEN_TYPE = 'Bearer';

    public function __construct(
        private readonly string $accessToken,
        private readonly DateTimeImmutable $expiresAt,
    ) {}

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @return array{accessToken: string, tokenType: string, expiresAt: string}
     */
    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'tokenType' => self::TOKEN_TYPE,
            'expiresAt' => $this->expiresAt
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
