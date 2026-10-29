<?php

namespace App\Application\Entity;

use DateTimeImmutable;

abstract class BaseEntity
{
    protected ?int $id = null;

    protected ?DateTimeImmutable $createdAt = null;

    protected ?DateTimeImmutable $updatedAt = null;

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
