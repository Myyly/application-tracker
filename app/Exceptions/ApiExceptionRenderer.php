<?php

namespace App\Exceptions;

use App\Constants\ErrorCode;
use App\Constants\HttpStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function supports(Request $request): bool
    {
        return $request->is('api/v1') || $request->is('api/v1/*');
    }

    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->supports($request)) {
            return null;
        }

        if ($exception instanceof ApiException) {
            return $this->jsonError(
                $exception->getMessage(),
                $exception->getErrorCode(),
                $exception->getStatusCode(),
                $exception->getErrors(),
            );
        }

        if ($exception instanceof LaravelValidationException) {
            return $this->jsonError(
                'Validation failed',
                ErrorCode::VALIDATION_ERROR,
                HttpStatus::UNPROCESSABLE_ENTITY,
                $exception->errors(),
            );
        }

        if ($exception instanceof AuthenticationException) {
            return $this->renderHttpStatus(HttpStatus::UNAUTHORIZED);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->renderHttpStatus(
                $exception->hasStatus() ? $exception->status() : HttpStatus::FORBIDDEN,
            );
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->renderHttpStatus(HttpStatus::NOT_FOUND);
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $this->renderHttpStatus(
                $exception->getStatusCode(),
                $exception->getHeaders(),
            );
        }

        return $this->renderHttpStatus(HttpStatus::INTERNAL_SERVER_ERROR);
    }

    /**
     * @param  array<string, string|string[]>  $headers
     */
    private function renderHttpStatus(int $statusCode, array $headers = []): JsonResponse
    {
        if ($statusCode >= HttpStatus::INTERNAL_SERVER_ERROR) {
            return $this->jsonError(
                'Internal server error',
                ErrorCode::INTERNAL_SERVER_ERROR,
                $statusCode,
                headers: $headers,
            );
        }

        [$message, $errorCode] = match ($statusCode) {
            HttpStatus::UNAUTHORIZED => ['Unauthenticated', ErrorCode::UNAUTHORIZED],
            HttpStatus::FORBIDDEN => ['Forbidden', ErrorCode::FORBIDDEN],
            HttpStatus::NOT_FOUND => ['Not found', ErrorCode::NOT_FOUND],
            HttpStatus::METHOD_NOT_ALLOWED => ['Method not allowed', ErrorCode::METHOD_NOT_ALLOWED],
            HttpStatus::UNPROCESSABLE_ENTITY => ['Validation failed', ErrorCode::VALIDATION_ERROR],
            HttpStatus::TOO_MANY_REQUESTS => ['Too many requests', ErrorCode::TOO_MANY_REQUESTS],
            default => [SymfonyResponse::$statusTexts[$statusCode] ?? 'HTTP error', ErrorCode::HTTP_ERROR],
        };

        return $this->jsonError($message, $errorCode, $statusCode, headers: $headers);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string|string[]>  $headers
     */
    private function jsonError(
        string $message,
        string $errorCode,
        int $statusCode,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'error_code' => $errorCode,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode, $headers);
    }
}
