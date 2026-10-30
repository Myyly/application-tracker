<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\AuthService;
use Illuminate\Http\JsonResponse;

final class EmailVerificationController extends AppApiController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function __invoke(int $id, string $hash): JsonResponse
    {
        $this->authService->verifyEmail($id, $hash);

        return $this->success(null, 'Email verified successfully');
    }
}
