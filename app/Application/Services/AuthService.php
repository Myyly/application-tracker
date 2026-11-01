<?php

namespace App\Application\Services;

use App\Application\DTO\Auth\LoginDTO;
use App\Application\DTO\Auth\LoginResultDTO;
use App\Application\DTO\Auth\RegisterDTO;
use App\Application\DTO\Auth\UpdateProfileDTO;
use App\Application\Entity\UserEntity;
use App\Exceptions\NotFoundException;
use App\Infrastructure\Auth\CurrentAccessTokenRevoker;
use App\Infrastructure\Auth\EmailVerificationResender;
use App\Infrastructure\Auth\EmailVerificationVerifier;
use App\Infrastructure\Auth\RegisteredUserDispatcher;
use App\Infrastructure\Auth\UserAuthenticator;
use App\Infrastructure\Repositories\UserRepository;
use App\Infrastructure\Storage\UserAvatarStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class AuthService
{
    public function __construct(
        private readonly CurrentAccessTokenRevoker $currentAccessTokenRevoker,
        private readonly EmailVerificationResender $emailVerificationResender,
        private readonly EmailVerificationVerifier $emailVerificationVerifier,
        private readonly RegisteredUserDispatcher $registeredUserDispatcher,
        private readonly UserAuthenticator $userAuthenticator,
        private readonly UserRepository $userRepository,
        private readonly UserAvatarStorage $userAvatarStorage,
    ) {}

    public function login(LoginDTO $dto): LoginResultDTO
    {
        return $this->userAuthenticator->authenticate($dto);
    }

    public function logout(int $currentAccessTokenId): void
    {
        $this->currentAccessTokenRevoker->revoke($currentAccessTokenId);
    }

    public function resendEmailVerification(int $userId): bool
    {
        return $this->emailVerificationResender->resend($userId);
    }

    public function getProfile(int $userId): UserEntity
    {
        $entity = $this->userRepository->findById($userId)
            ?? throw new NotFoundException;

        return $this->withAvatarUrl($entity);
    }

    public function updateProfile(int $userId, UpdateProfileDTO $dto): UserEntity
    {
        return $this->withAvatarUrl(
            $this->userRepository->updateProfile($userId, $dto),
        );
    }

    public function uploadAvatar(int $userId, UploadedFile $avatar): UserEntity
    {
        $newAvatarPath = $this->userAvatarStorage->store($avatar);

        try {
            // Keep the new file only after its relative path has been persisted successfully.
            $result = $this->userRepository->replaceAvatarPath($userId, $newAvatarPath);
        } catch (Throwable $exception) {
            $this->deleteAvatarSafely(
                $newAvatarPath,
                $userId,
                'Failed to clean up a new avatar after a database error.',
            );

            throw $exception;
        }

        $previousAvatarPath = $result['previousAvatarPath'];

        // Delete only an application-owned previous avatar after the database points to the new file.
        if ($previousAvatarPath !== $newAvatarPath) {
            $this->deleteAvatarSafely(
                $previousAvatarPath,
                $userId,
                'Failed to delete a previous user avatar.',
            );
        }

        return $this->withAvatarUrl($result['entity']);
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

    private function withAvatarUrl(UserEntity $entity): UserEntity
    {
        $entity->{UserEntity::PROPERTY_AVATAR_URL} = $this->userAvatarStorage->url(
            $entity->getAvatarPath(),
        );

        return $entity;
    }

    private function deleteAvatarSafely(?string $path, int $userId, string $logMessage): void
    {
        try {
            $deleted = $this->userAvatarStorage->delete($path);
        } catch (Throwable $exception) {
            Log::warning($logMessage, [
                'user_id' => $userId,
                'exception_class' => $exception::class,
            ]);

            return;
        }

        if (! $deleted) {
            Log::warning($logMessage, ['user_id' => $userId]);
        }
    }
}
