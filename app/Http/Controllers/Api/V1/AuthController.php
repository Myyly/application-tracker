<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\DTO\Auth\LoginDTO;
use App\Application\DTO\Auth\RegisterDTO;
use App\Application\DTO\Auth\ResetPasswordDTO;
use App\Application\DTO\Auth\UpdateProfileDTO;
use App\Application\Services\AuthService;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;

final class AuthController extends AppApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserTransformer $userTransformer,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->authService->login(new LoginDTO(
            email: $validated['email'],
            password: $validated['password'],
        ));

        return $this->success($result->toArray());
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return $this->success(
            null,
            'If an account exists for this email, a password reset link will be sent.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authService->resetPassword(new ResetPasswordDTO(
            email: $validated['email'],
            token: $validated['token'],
            password: $validated['password'],
        ));

        return $this->success(null, 'Password reset successfully');
    }

    public function logout(Request $request): JsonResponse
    {
        // Sanctum attaches the exact bearer token used for this request; this is not a session logout.
        $currentAccessToken = $request->user()->currentAccessToken();

        if (! $currentAccessToken instanceof PersonalAccessToken) {
            throw new LogicException('A bearer-authenticated request must have a personal access token.');
        }

        $this->authService->logout(
            (int) $currentAccessToken->getKey(),
        );

        return $this->success(null, 'Logged out successfully');
    }

    public function resendEmailVerification(Request $request): JsonResponse
    {
        $notificationSent = $this->authService->resendEmailVerification(
            (int) $request->user()->getAuthIdentifier(),
        );

        return $this->success(
            null,
            $notificationSent
                ? 'Verification email sent successfully'
                : 'Email already verified',
        );
    }

    public function profile(Request $request): JsonResponse
    {
        // Sanctum authenticates the bearer token first; this ID comes from auth context, never client input.
        $userId = (int) $request->user()->getAuthIdentifier();
        $entity = $this->authService->getProfile($userId);

        return $this->success($this->userTransformer->transformForProfile($entity));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        // The DTO distinguishes omitted fields from explicit null so PATCH can preserve or clear values.
        $dto = UpdateProfileDTO::fromValidated($request->validated());
        $userId = (int) $request->user()->getAuthIdentifier();
        $entity = $this->authService->updateProfile($userId, $dto);

        return $this->success($this->userTransformer->transformForProfile($entity));
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        /** @var UploadedFile $avatar */
        $avatar = $request->file('avatar');
        $entity = $this->authService->uploadAvatar($userId, $avatar);

        return $this->success($this->userTransformer->transformForProfile($entity));
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $dto = new RegisterDTO(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
        );

        $entity = $this->authService->register($dto);

        return $this->created(
            $this->userTransformer->transformForRegister($entity),
            'User registered successfully',
        );
    }
}
