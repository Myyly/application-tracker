<?php

namespace App\Application\Services;

use App\Application\DTO\Auth\RegisterDTO;
use App\Application\Entity\UserEntity;
use App\Infrastructure\Auth\EmailVerificationVerifier;
use App\Infrastructure\Auth\RegisteredUserDispatcher;
use App\Infrastructure\Repositories\UserRepository;
use LogicException;

final class AuthService
{
    public function __construct(
        private readonly EmailVerificationVerifier $emailVerificationVerifier,
        private readonly RegisteredUserDispatcher $registeredUserDispatcher,
        private readonly UserRepository $userRepository,
    ) {}

    public function register(RegisterDTO $dto): UserEntity
    {
        $entity = $this->userRepository->create($dto);
        $userId = $entity->getId();

        if ($userId === null) {
            throw new LogicException('A persisted user must have an ID.');
        }

        $this->registeredUserDispatcher->dispatch($userId);

        return $entity;
    }

    public function verifyEmail(int $id, string $hash): void
    {
        $this->emailVerificationVerifier->verify($id, $hash);
    }
}
