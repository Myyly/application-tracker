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

    public function test_database_field_constants_match_common_column_names(): void
    {
        $this->assertSame('id', BaseEntity::ID);
        $this->assertSame('created_at', BaseEntity::CREATED_AT);
        $this->assertSame('updated_at', BaseEntity::UPDATED_AT);
    }

    public function test_property_constants_match_common_entity_property_names(): void
    {
        $this->assertSame('id', BaseEntity::PROPERTY_ID);
        $this->assertSame('createdAt', BaseEntity::PROPERTY_CREATED_AT);
        $this->assertSame('updatedAt', BaseEntity::PROPERTY_UPDATED_AT);
    }

    public function test_common_fields_default_to_null(): void
    {
        $entity = new TestEntity;

        $this->assertNull($entity->id);
        $this->assertNull($entity->createdAt);
        $this->assertNull($entity->updatedAt);
        $this->assertNull($entity->getId());
        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }

    public function test_common_fields_can_be_assigned_directly_and_read_through_getters(): void
    {
        $createdAt = new DateTimeImmutable('2026-10-28 09:00:00');
        $updatedAt = new DateTimeImmutable('2026-10-29 10:30:00');
        $entity = new TestEntity;

        $entity->id = 42;
        $entity->createdAt = $createdAt;
        $entity->updatedAt = $updatedAt;

        $this->assertSame(42, $entity->id);
        $this->assertSame($createdAt, $entity->createdAt);
        $this->assertSame($updatedAt, $entity->updatedAt);
        $this->assertSame(42, $entity->getId());
        $this->assertSame($createdAt, $entity->getCreatedAt());
        $this->assertSame($updatedAt, $entity->getUpdatedAt());
    }
}

final class TestEntity extends BaseEntity {}
