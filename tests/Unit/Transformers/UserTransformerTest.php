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
}
