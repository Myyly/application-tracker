<?php

namespace App\Application\Services;

use App\Application\DTO\Auth\LoginDTO;
use App\Application\DTO\Auth\LoginResultDTO;
use App\Application\DTO\Auth\RegisterDTO;
use App\Application\DTO\Auth\UpdateProfileDTO;
use App\Application\Entity\UserEntity;
use App\Exceptions\NotFoundException;
use App\Infrastructure\Auth\EmailVerificationVerifier;
use App\Infrastructure\Auth\RegisteredUserDispatcher;
use App\Infrastructure\Auth\UserAuthenticator;
use App\Infrastructure\Repositories\UserRepository;
use LogicException;

final class AuthService
{
    public function __construct(
        private readonly EmailVerificationVerifier $emailVerificationVerifier,
        private readonly RegisteredUserDispatcher $registeredUserDispatcher,
        private readonly UserAuthenticator $userAuthenticator,
        private readonly UserRepository $userRepository,
    ) {}

    public function login(LoginDTO $dto): LoginResultDTO
    {
        return $this->userAuthenticator->authenticate($dto);
    }

    public function getProfile(int $userId): UserEntity
    {
        return $this->userRepository->findById($userId)
            ?? throw new NotFoundException;
    }

    public function updateProfile(int $userId, UpdateProfileDTO $dto): UserEntity
    {
        return $this->userRepository->updateProfile($userId, $dto);
    }

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
