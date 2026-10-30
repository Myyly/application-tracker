<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\DTO\Auth\RegisterDTO;
use App\Application\Services\AuthService;
use App\Http\Requests\Auth\RegisterRequest;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;

final class AuthController extends AppApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserTransformer $userTransformer,
    ) {}

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
