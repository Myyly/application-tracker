<?php

namespace Tests\Unit\Application\Entity;

use App\Application\Entity\BaseEntity;
use App\Application\Entity\UserEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserEntityTest extends TestCase
{
    public function test_composer_autoloads_the_user_entity(): void
    {
        $this->assertTrue(class_exists(UserEntity::class));
    }

    public function test_user_entity_extends_the_base_entity(): void
    {
        $entity = new UserEntity;

        $this->assertInstanceOf(BaseEntity::class, $entity);
    }

    public function test_database_field_constants_match_user_table_and_column_names(): void
    {
        $this->assertSame('users', UserEntity::TABLE_NAME);
        $this->assertSame('id', UserEntity::ID);
        $this->assertSame('name', UserEntity::NAME);
        $this->assertSame('email', UserEntity::EMAIL);
        $this->assertSame('password', UserEntity::PASSWORD);
        $this->assertSame('date_of_birth', UserEntity::DATE_OF_BIRTH);
        $this->assertSame('gender', UserEntity::GENDER);
        $this->assertSame('phone', UserEntity::PHONE);
        $this->assertSame('avatar_path', UserEntity::AVATAR_PATH);
        $this->assertSame('email_verified_at', UserEntity::EMAIL_VERIFIED_AT);
        $this->assertSame('created_at', UserEntity::CREATED_AT);
        $this->assertSame('updated_at', UserEntity::UPDATED_AT);
    }

    public function test_property_constants_match_camel_case_entity_property_names(): void
    {
        $this->assertSame('id', UserEntity::PROPERTY_ID);
        $this->assertSame('name', UserEntity::PROPERTY_NAME);
        $this->assertSame('email', UserEntity::PROPERTY_EMAIL);
        $this->assertSame('dateOfBirth', UserEntity::PROPERTY_DATE_OF_BIRTH);
        $this->assertSame('gender', UserEntity::PROPERTY_GENDER);
        $this->assertSame('phone', UserEntity::PROPERTY_PHONE);
        $this->assertSame('avatarPath', UserEntity::PROPERTY_AVATAR_PATH);
        $this->assertSame('emailVerifiedAt', UserEntity::PROPERTY_EMAIL_VERIFIED_AT);
        $this->assertSame('createdAt', UserEntity::PROPERTY_CREATED_AT);
        $this->assertSame('updatedAt', UserEntity::PROPERTY_UPDATED_AT);
    }

    public function test_all_fields_default_to_null_without_constructor_arguments(): void
    {
        $entity = new UserEntity;

        $this->assertNull($entity->id);
        $this->assertNull($entity->name);
        $this->assertNull($entity->email);
        $this->assertNull($entity->dateOfBirth);
        $this->assertNull($entity->gender);
        $this->assertNull($entity->phone);
        $this->assertNull($entity->avatarPath);
        $this->assertNull($entity->emailVerifiedAt);
        $this->assertNull($entity->createdAt);
        $this->assertNull($entity->updatedAt);
    }

    public function test_public_fields_can_be_assigned_directly_and_read_through_getters(): void
    {
        $emailVerifiedAt = new DateTimeImmutable('2026-10-29 08:30:00');
        $createdAt = new DateTimeImmutable('2026-10-28 09:00:00');
        $updatedAt = new DateTimeImmutable('2026-10-29 10:30:00');
        $entity = new UserEntity;

        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';
        $entity->dateOfBirth = '2000-05-20';
        $entity->gender = USER_GENDER_FEMALE;
        $entity->phone = '0901234567';
        $entity->avatarPath = 'avatars/my-ly.jpg';
        $entity->emailVerifiedAt = $emailVerifiedAt;
        $entity->createdAt = $createdAt;
        $entity->updatedAt = $updatedAt;

        $this->assertSame(42, $entity->id);
        $this->assertSame('My Ly', $entity->name);
        $this->assertSame('myly@example.com', $entity->email);
        $this->assertSame('2000-05-20', $entity->dateOfBirth);
        $this->assertSame(USER_GENDER_FEMALE, $entity->gender);
        $this->assertSame('0901234567', $entity->phone);
        $this->assertSame('avatars/my-ly.jpg', $entity->avatarPath);
        $this->assertSame($emailVerifiedAt, $entity->emailVerifiedAt);
        $this->assertSame($createdAt, $entity->createdAt);
        $this->assertSame($updatedAt, $entity->updatedAt);
        $this->assertSame('My Ly', $entity->getName());
        $this->assertSame('myly@example.com', $entity->getEmail());
        $this->assertSame('2000-05-20', $entity->getDateOfBirth());
        $this->assertSame(USER_GENDER_FEMALE, $entity->getGender());
        $this->assertSame('0901234567', $entity->getPhone());
        $this->assertSame('avatars/my-ly.jpg', $entity->getAvatarPath());
        $this->assertSame($emailVerifiedAt, $entity->getEmailVerifiedAt());
        $this->assertSame(42, $entity->getId());
        $this->assertSame($createdAt, $entity->getCreatedAt());
        $this->assertSame($updatedAt, $entity->getUpdatedAt());
    }

    public function test_to_array_returns_only_basic_login_fields(): void
    {
        $entity = new UserEntity;

        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';
        $entity->dateOfBirth = '2000-05-20';
        $entity->gender = USER_GENDER_MALE;
        $entity->phone = '0901234567';
        $entity->avatarPath = 'avatars/private.jpg';
        $entity->emailVerifiedAt = new DateTimeImmutable('2026-10-30 15:00:00+07:00');
        $entity->createdAt = new DateTimeImmutable('2026-10-29 08:00:00+07:00');
        $entity->updatedAt = new DateTimeImmutable('2026-10-30 09:00:00+07:00');

        $this->assertSame([
            'id' => 42,
            'name' => 'My Ly',
            'email' => 'myly@example.com',
        ], $entity->toArray());
    }

    public function test_to_register_array_returns_exact_camel_case_contract(): void
    {
        $entity = new UserEntity;
        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';
        $entity->emailVerifiedAt = new DateTimeImmutable('2026-10-30 15:00:00+07:00');

        $this->assertSame([
            'id' => 42,
            'name' => 'My Ly',
            'email' => 'myly@example.com',
            'emailVerifiedAt' => '2026-10-30T15:00:00+07:00',
        ], $entity->toRegisterArray());
    }

    public function test_to_detail_array_returns_only_api_safe_camel_case_fields(): void
    {
        $entity = new UserEntity;
        $entity->id = 42;
        $entity->name = 'My Ly';
        $entity->email = 'myly@example.com';
        $entity->dateOfBirth = '2000-05-20';
        $entity->gender = USER_GENDER_MALE;
        $entity->phone = '0901234567';
        $entity->avatarPath = 'avatars/private.jpg';
        $entity->emailVerifiedAt = new DateTimeImmutable('2026-10-30 15:00:00+07:00');
        $entity->createdAt = new DateTimeImmutable('2026-10-29 08:00:00+07:00');
        $entity->updatedAt = new DateTimeImmutable('2026-10-30 09:00:00+07:00');

        $this->assertSame([
            'name' => 'My Ly',
            'email' => 'myly@example.com',
            'dateOfBirth' => '2000-05-20',
            'gender' => USER_GENDER_MALE,
            'phone' => '0901234567',
            'avatarUrl' => null,
            'emailVerifiedAt' => '2026-10-30T15:00:00+07:00',
        ], $entity->toDetailArray());
    }

    public function test_output_methods_preserve_nullable_values_and_do_not_mutate_entity(): void
    {
        $entity = new UserEntity;

        $this->assertSame([
            'id' => null,
            'name' => null,
            'email' => null,
        ], $entity->toArray());
        $this->assertSame([
            'id' => null,
            'name' => null,
            'email' => null,
            'emailVerifiedAt' => null,
        ], $entity->toRegisterArray());
        $this->assertSame([
            'name' => null,
            'email' => null,
            'dateOfBirth' => null,
            'gender' => null,
            'phone' => null,
            'avatarUrl' => null,
            'emailVerifiedAt' => null,
        ], $entity->toDetailArray());

        $this->assertNull($entity->getName());
        $this->assertNull($entity->getEmail());
        $this->assertNull($entity->getDateOfBirth());
        $this->assertNull($entity->getGender());
        $this->assertNull($entity->getPhone());
        $this->assertNull($entity->getAvatarPath());
        $this->assertNull($entity->getEmailVerifiedAt());
        $this->assertNull($entity->getId());
        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }
}
