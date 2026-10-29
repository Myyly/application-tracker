<?php

namespace Tests\Unit\Application\Entity;

use App\Application\Entity\BaseEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BaseEntityTest extends TestCase
{
    public function test_composer_autoloads_the_base_entity(): void
    {
        $this->assertTrue(class_exists(BaseEntity::class));
    }

    public function test_a_concrete_entity_can_extend_the_base_entity(): void
    {
        $this->assertInstanceOf(BaseEntity::class, new TestEntity);
    }

    public function test_common_fields_default_to_null(): void
    {
        $entity = new TestEntity;

        $this->assertNull($entity->getId());
        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }

    public function test_common_fields_preserve_values_initialized_by_a_child_entity(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-28 09:00:00');
        $updatedAt = new DateTimeImmutable('2026-10-29 10:30:00');
        $entity = new TestEntity(42, $createdAt, $updatedAt);

        $this->assertSame(42, $entity->getId());
        $this->assertSame($createdAt, $entity->getCreatedAt());
        $this->assertSame($updatedAt, $entity->getUpdatedAt());
    }
}

final class TestEntity extends BaseEntity
{
    public function __construct(
        ?int $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}
