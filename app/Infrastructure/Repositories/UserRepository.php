<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTO\Auth\RegisterDTO;
use App\Application\Entity\UserEntity;
use App\Models\User;

class UserRepository
{
    public function create(RegisterDTO $dto): UserEntity
    {
        $model = User::query()->create([
            UserEntity::NAME => $dto->getName(),
            UserEntity::EMAIL => $dto->getEmail(),
            UserEntity::PASSWORD => $dto->getPassword(),
        ]);

        return $this->toEntity($model);
    }

    public function findById(int $id): ?UserEntity
    {
        $model = User::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findByEmail(string $email): ?UserEntity
    {
        $model = User::query()->where(UserEntity::EMAIL, $email)->first();

        return $model === null ? null : $this->toEntity($model);
    }

    private function toEntity(User $model): UserEntity
    {
        $entity = new UserEntity;

        $entity->{UserEntity::PROPERTY_ID} = $model->getKey();
        $entity->{UserEntity::PROPERTY_NAME} = $model->{UserEntity::NAME};
        $entity->{UserEntity::PROPERTY_EMAIL} = $model->{UserEntity::EMAIL};
        $entity->{UserEntity::PROPERTY_DATE_OF_BIRTH} = $model->{UserEntity::DATE_OF_BIRTH}?->toDateString();
        $entity->{UserEntity::PROPERTY_GENDER} = $model->{UserEntity::GENDER};
        $entity->{UserEntity::PROPERTY_PHONE} = $model->{UserEntity::PHONE};
        $entity->{UserEntity::PROPERTY_AVATAR_PATH} = $model->{UserEntity::AVATAR_PATH};
        $entity->{UserEntity::PROPERTY_EMAIL_VERIFIED_AT} = $model->{UserEntity::EMAIL_VERIFIED_AT}?->toDateTimeImmutable();
        $entity->{UserEntity::PROPERTY_CREATED_AT} = $model->{UserEntity::CREATED_AT}?->toDateTimeImmutable();
        $entity->{UserEntity::PROPERTY_UPDATED_AT} = $model->{UserEntity::UPDATED_AT}?->toDateTimeImmutable();

        return $entity;
    }
}
