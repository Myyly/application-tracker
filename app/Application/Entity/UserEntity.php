<?php

namespace App\Application\Entity;

use DateTimeImmutable;

class UserEntity extends BaseEntity
{
    public const TABLE_NAME = 'users';

    public const NAME = 'name';

    public const EMAIL = 'email';

    public const PASSWORD = 'password';

    public const DATE_OF_BIRTH = 'date_of_birth';

    public const GENDER = 'gender';

    public const PHONE = 'phone';

    public const AVATAR_PATH = 'avatar_path';

    public const EMAIL_VERIFIED_AT = 'email_verified_at';

    public const PROPERTY_NAME = 'name';

    public const PROPERTY_EMAIL = 'email';

    public const PROPERTY_DATE_OF_BIRTH = 'dateOfBirth';

    public const PROPERTY_GENDER = 'gender';

    public const PROPERTY_PHONE = 'phone';

    public const PROPERTY_AVATAR_PATH = 'avatarPath';

    public const PROPERTY_EMAIL_VERIFIED_AT = 'emailVerifiedAt';

    public ?string $name = null;

    public ?string $email = null;

    public ?string $dateOfBirth = null;

    public ?int $gender = null;

    public ?string $phone = null;

    public ?string $avatarPath = null;

    public ?DateTimeImmutable $emailVerifiedAt = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getDateOfBirth(): ?string
    {
        return $this->dateOfBirth;
    }

    public function getGender(): ?int
    {
        return $this->gender;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    /**
     * @return array{id: int|null, name: string|null, email: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    /**
     * @return array{id: int|null, name: string|null, email: string|null, emailVerifiedAt: string|null}
     */
    public function toRegisterArray(): array
    {
        return [
            ...$this->toArray(),
            'emailVerifiedAt' => $this->emailVerifiedAt?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array{name: string|null, email: string|null, dateOfBirth: string|null, gender: int|null, phone: string|null, avatarUrl: null, emailVerifiedAt: string|null}
     */
    public function toDetailArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'dateOfBirth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'avatarUrl' => null,
            'emailVerifiedAt' => $this->emailVerifiedAt?->format(DATE_ATOM),
        ];
    }
}
