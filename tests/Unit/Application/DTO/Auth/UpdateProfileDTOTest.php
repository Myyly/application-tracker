<?php

namespace Tests\Unit\Application\DTO\Auth;

use App\Application\DTO\Auth\UpdateProfileDTO;
use PHPUnit\Framework\TestCase;

class UpdateProfileDTOTest extends TestCase
{
    public function test_returns_values_and_marks_provided_fields(): void
    {
        $dto = UpdateProfileDTO::fromValidated([
            'name' => 'My Ly',
            'dateOfBirth' => '2000-05-20',
            'gender' => USER_GENDER_MALE,
            'phone' => '0901234567',
        ]);

        $this->assertSame('My Ly', $dto->getName());
        $this->assertSame('2000-05-20', $dto->getDateOfBirth());
        $this->assertSame(USER_GENDER_MALE, $dto->getGender());
        $this->assertSame('0901234567', $dto->getPhone());
        $this->assertTrue($dto->hasName());
        $this->assertTrue($dto->hasDateOfBirth());
        $this->assertTrue($dto->hasGender());
        $this->assertTrue($dto->hasPhone());
    }

    public function test_marks_omitted_fields_as_not_provided(): void
    {
        $dto = UpdateProfileDTO::fromValidated([]);

        $this->assertNull($dto->getName());
        $this->assertNull($dto->getDateOfBirth());
        $this->assertNull($dto->getGender());
        $this->assertNull($dto->getPhone());
        $this->assertFalse($dto->hasName());
        $this->assertFalse($dto->hasDateOfBirth());
        $this->assertFalse($dto->hasGender());
        $this->assertFalse($dto->hasPhone());
    }

    public function test_preserves_explicit_null_as_provided(): void
    {
        $dto = UpdateProfileDTO::fromValidated([
            'name' => null,
            'dateOfBirth' => null,
            'gender' => null,
            'phone' => null,
        ]);

        $this->assertNull($dto->getName());
        $this->assertNull($dto->getDateOfBirth());
        $this->assertNull($dto->getGender());
        $this->assertNull($dto->getPhone());
        $this->assertTrue($dto->hasName());
        $this->assertTrue($dto->hasDateOfBirth());
        $this->assertTrue($dto->hasGender());
        $this->assertTrue($dto->hasPhone());
    }
}
