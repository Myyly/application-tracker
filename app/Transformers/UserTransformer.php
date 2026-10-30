<?php

namespace App\Transformers;

use App\Application\Entity\UserEntity;

final class UserTransformer
{
    /**
     * @return array{id: int|null, name: string|null, email: string|null, emailVerifiedAt: string|null}
     */
    public function transformForRegister(UserEntity $entity): array
    {
        return $entity->toRegisterArray();
    }

    /**
     * @return array{id: int|null, name: string|null, email: string|null}
     */
    public function transformForLogin(UserEntity $entity): array
    {
        return $entity->toArray();
    }

    /**
     * @return array{name: string|null, email: string|null, dateOfBirth: string|null, gender: int|null, phone: string|null, avatarUrl: null, emailVerifiedAt: string|null}
     */
    public function transformForProfile(UserEntity $entity): array
    {
        return $entity->toDetailArray();
    }
}
