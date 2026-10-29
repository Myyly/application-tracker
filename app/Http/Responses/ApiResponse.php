<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    public static function success(mixed $data, ?string $message = null): JsonResponse
    {
        return self::json(['data' => $data], Response::HTTP_OK, $message);
    }

    public static function created(mixed $data, ?string $message = null): JsonResponse
    {
        return self::json(['data' => $data], Response::HTTP_CREATED, $message);
    }

    public static function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * @param  array{first: string|null, last: string|null, prev: string|null, next: string|null}  $links
     * @param  array{current_page: int, last_page: int, per_page: int, total: int}  $meta
     */
    public static function paginated(
        array $data,
        array $links,
        array $meta,
        ?string $message = null,
    ): JsonResponse {
        return self::json([
            'data' => $data,
            'links' => $links,
            'meta' => $meta,
        ], Response::HTTP_OK, $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function json(array $payload, int $status, ?string $message): JsonResponse
    {
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }
}
