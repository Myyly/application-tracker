<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

abstract class AppApiController extends Controller
{
    protected function success(mixed $data, ?string $message = null): JsonResponse
    {
        return ApiResponse::success($data, $message);
    }

    protected function created(mixed $data, ?string $message = null): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function noContent(): Response
    {
        return ApiResponse::noContent();
    }

    /**
     * @param  array{first: string|null, last: string|null, prev: string|null, next: string|null}  $links
     * @param  array{current_page: int, last_page: int, per_page: int, total: int}  $meta
     */
    protected function paginated(
        array $data,
        array $links,
        array $meta,
        ?string $message = null,
    ): JsonResponse {
        return ApiResponse::paginated($data, $links, $meta, $message);
    }

    protected function file(
        string $content,
        string $filename,
        string $mimeType,
        string $disposition = HeaderUtils::DISPOSITION_ATTACHMENT,
    ): Response {
        $fallbackFilename = str_replace('%', '', Str::ascii($filename));
        $contentDisposition = HeaderUtils::makeDisposition(
            $disposition,
            $filename,
            $fallbackFilename,
        );

        return response($content, Response::HTTP_OK, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $contentDisposition,
        ]);
    }
}
