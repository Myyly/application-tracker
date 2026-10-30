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
}
