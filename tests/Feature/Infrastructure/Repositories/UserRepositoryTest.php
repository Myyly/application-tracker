<?php

namespace Tests\Feature\Infrastructure\Repositories;

use App\Application\Entity\UserEntity;
use App\Infrastructure\Repositories\UserRepository;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_fully_mapped_user_entity_when_id_exists(): void
    {
        $model = User::factory()->create([
            'name' => 'My Ly',
            'email' => 'myly@example.com',
            'date_of_birth' => '2000-05-20',
            'gender' => USER_GENDER_FEMALE,
            'phone' => '0901234567',
            'avatar_path' => 'avatars/my-ly.jpg',
            'email_verified_at' => '2026-10-29 08:30:00',
            'created_at' => '2026-10-28 09:00:00',
            'updated_at' => '2026-10-29 10:30:00',
        ]);

        $entity = (new UserRepository)->findById($model->getKey());

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertSame($model->getKey(), $entity->id);
        $this->assertSame('My Ly', $entity->name);
        $this->assertSame('myly@example.com', $entity->email);
        $this->assertSame('2000-05-20', $entity->dateOfBirth);
        $this->assertSame(USER_GENDER_FEMALE, $entity->gender);
        $this->assertSame('0901234567', $entity->phone);
        $this->assertSame('avatars/my-ly.jpg', $entity->avatarPath);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->emailVerifiedAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->updatedAt);
        $this->assertSame($model->getKey(), $entity->getId());
        $this->assertSame('My Ly', $entity->getName());
        $this->assertSame('myly@example.com', $entity->getEmail());
        $this->assertSame('2000-05-20', $entity->getDateOfBirth());
        $this->assertSame(USER_GENDER_FEMALE, $entity->getGender());
        $this->assertSame('0901234567', $entity->getPhone());
        $this->assertSame('avatars/my-ly.jpg', $entity->getAvatarPath());
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->getEmailVerifiedAt());
        $this->assertSame('2026-10-29 08:30:00', $entity->getEmailVerifiedAt()->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->getCreatedAt());
        $this->assertSame('2026-10-28 09:00:00', $entity->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $entity->getUpdatedAt());
        $this->assertSame('2026-10-29 10:30:00', $entity->getUpdatedAt()->format('Y-m-d H:i:s'));
    }

    public function test_returns_null_when_id_does_not_exist(): void
    {
        $entity = (new UserRepository)->findById(999);

        $this->assertNull($entity);
    }

    public function test_returns_user_entity_when_email_exists(): void
    {
        $model = User::factory()->create([
            'email' => 'myly@example.com',
        ]);

        $entity = (new UserRepository)->findByEmail('myly@example.com');

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertSame($model->getKey(), $entity->getId());
        $this->assertSame('myly@example.com', $entity->getEmail());
    }

    public function test_returns_null_when_email_does_not_exist(): void
    {
        $entity = (new UserRepository)->findByEmail('missing@example.com');

        $this->assertNull($entity);
    }

    public function test_maps_nullable_fields_to_null(): void
    {
        $model = User::factory()->unverified()->create([
            'date_of_birth' => null,
            'gender' => null,
            'phone' => null,
            'avatar_path' => null,
            'created_at' => null,
            'updated_at' => null,
        ]);

        $entity = (new UserRepository)->findById($model->getKey());

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertNull($entity->dateOfBirth);
        $this->assertNull($entity->gender);
        $this->assertNull($entity->phone);
        $this->assertNull($entity->avatarPath);
        $this->assertNull($entity->emailVerifiedAt);
        $this->assertNull($entity->createdAt);
        $this->assertNull($entity->updatedAt);
        $this->assertNull($entity->getDateOfBirth());
        $this->assertNull($entity->getGender());
        $this->assertNull($entity->getPhone());
        $this->assertNull($entity->getAvatarPath());
        $this->assertNull($entity->getEmailVerifiedAt());
        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }

    public function test_maps_zero_gender_value_as_integer(): void
    {
        $model = User::factory()->create([
            'gender' => USER_GENDER_MALE,
        ]);

        $entity = (new UserRepository)->findById($model->getKey());

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertSame(USER_GENDER_MALE, $entity->getGender());
    }

    public function test_does_not_expose_password_or_tokens_on_user_entity(): void
    {
        $model = User::factory()->create();

        $entity = (new UserRepository)->findById($model->getKey());

        $this->assertInstanceOf(UserEntity::class, $entity);
        $this->assertFalse(property_exists($entity, 'password'));
        $this->assertFalse(property_exists($entity, 'rememberToken'));
        $this->assertFalse(property_exists($entity, 'tokens'));
    }
}
