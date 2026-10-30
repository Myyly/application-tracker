<?php

namespace App\Application\Entity;

use DateTimeImmutable;

abstract class BaseEntity
{
    public const ID = 'id';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    public const PROPERTY_ID = 'id';

    public const PROPERTY_CREATED_AT = 'createdAt';

    public const PROPERTY_UPDATED_AT = 'updatedAt';

    public ?int $id = null;

    public ?DateTimeImmutable $createdAt = null;

    public ?DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
