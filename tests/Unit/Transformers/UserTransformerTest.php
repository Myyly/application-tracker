<?php

namespace Tests\Unit\Transformers;

use App\Application\Entity\UserEntity;
use App\Transformers\UserTransformer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserTransformerTest extends TestCase
{
    public function test_transforms_register_response_with_exact_fields(): void
    {
        $entity = new UserEntity;
        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';
        $entity->emailVerifiedAt = new DateTimeImmutable('2026-10-30 15:00:00+07:00');

        $data = (new UserTransformer)->transformForRegister($entity);

        $this->assertSame([
            'id' => 42,
            'name' => 'My Ly',
            'email' => 'myly@example.com',
            'emailVerifiedAt' => '2026-10-30T15:00:00+07:00',
        ], $data);
    }

    public function test_transforms_login_response_with_exact_fields(): void
    {
        $entity = new UserEntity;
        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';

        $data = (new UserTransformer)->transformForLogin($entity);

        $this->assertSame([
            'id' => 42,
            'name' => 'My Ly',
            'email' => 'myly@example.com',
        ], $data);
    }

    public function test_transforms_profile_response_with_exact_fields(): void
    {
        $entity = new UserEntity;
        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';
        $entity->dateOfBirth = '2000-05-20';
        $entity->gender = USER_GENDER_FEMALE;
        $entity->phone = '0901234567';
        $entity->avatarPath = 'avatars/private.jpg';
        $entity->emailVerifiedAt = new DateTimeImmutable('2026-10-30 15:00:00+07:00');
        $entity->createdAt = new DateTimeImmutable('2026-10-29 08:00:00+07:00');
        $entity->updatedAt = new DateTimeImmutable('2026-10-30 09:00:00+07:00');

        $data = (new UserTransformer)->transformForProfile($entity);

        $this->assertSame([
            'name' => 'My Ly',
            'email' => 'myly@example.com',
            'dateOfBirth' => '2000-05-20',
            'gender' => USER_GENDER_FEMALE,
            'phone' => '0901234567',
            'avatarUrl' => null,
            'emailVerifiedAt' => '2026-10-30T15:00:00+07:00',
        ], $data);
    }

    public function test_preserves_nullable_profile_values(): void
    {
        $entity = new UserEntity;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';

        $data = (new UserTransformer)->transformForProfile($entity);

        $this->assertSame([
            'name' => 'My Ly',
            'email' => 'myly@example.com',
            'dateOfBirth' => null,
            'gender' => null,
            'phone' => null,
            'avatarUrl' => null,
            'emailVerifiedAt' => null,
        ], $data);
    }

    public function test_does_not_mutate_user_entity(): void
    {
        $emailVerifiedAt = new DateTimeImmutable('2026-10-30 15:00:00+07:00');
        $entity = new UserEntity;
        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';
        $entity->dateOfBirth = '2000-05-20';
        $entity->gender = USER_GENDER_MALE;
        $entity->phone = '0901234567';
        $entity->emailVerifiedAt = $emailVerifiedAt;

        (new UserTransformer)->transformForProfile($entity);

        $this->assertSame(42, $entity->getId());
        $this->assertSame('My Ly', $entity->getName());
        $this->assertSame('myly@example.com', $entity->getEmail());
        $this->assertSame('2000-05-20', $entity->getDateOfBirth());
        $this->assertSame(USER_GENDER_MALE, $entity->getGender());
        $this->assertSame('0901234567', $entity->getPhone());
        $this->assertSame($emailVerifiedAt, $entity->getEmailVerifiedAt());
    }
}
