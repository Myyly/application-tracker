<?php

namespace App\Application\DTO\Auth;

final class UpdateProfileDTO
{
    /**
     * @param  array{name: bool, dateOfBirth: bool, gender: bool, phone: bool}  $providedFields
     */
    private function __construct(
        private readonly ?string $name,
        private readonly ?string $dateOfBirth,
        private readonly ?int $gender,
        private readonly ?string $phone,
        private readonly array $providedFields,
    ) {}

    /**
     * @param  array{name?: string|null, dateOfBirth?: string|null, gender?: int|null, phone?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'] ?? null,
            dateOfBirth: $validated['dateOfBirth'] ?? null,
            gender: $validated['gender'] ?? null,
            phone: $validated['phone'] ?? null,
            providedFields: [
                'name' => array_key_exists('name', $validated),
                'dateOfBirth' => array_key_exists('dateOfBirth', $validated),
                'gender' => array_key_exists('gender', $validated),
                'phone' => array_key_exists('phone', $validated),
            ],
        );
    }

    public function getName(): ?string
    {
        return $this->name;
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

    public function hasName(): bool
    {
        return $this->providedFields['name'];
    }

    public function hasDateOfBirth(): bool
    {
        return $this->providedFields['dateOfBirth'];
    }

    public function hasGender(): bool
    {
        return $this->providedFields['gender'];
    }

    public function hasPhone(): bool
    {
        return $this->providedFields['phone'];
    }
}
