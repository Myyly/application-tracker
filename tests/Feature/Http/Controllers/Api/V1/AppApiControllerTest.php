<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\AppApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Tests\TestCase;

class AppApiControllerTest extends TestCase
{
    public function test_success_returns_the_standard_200_response(): void
    {
        $controller = new TestAppApiController;

        $response = TestResponse::fromBaseResponse($controller->successResponse(
            ['id' => 1],
            'Success.',
        ));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => ['id' => 1],
                'message' => 'Success.',
            ]);
    }

    public function test_created_returns_the_standard_201_response(): void
    {
        $controller = new TestAppApiController;

        $response = TestResponse::fromBaseResponse($controller->createdResponse(
            ['id' => 1],
            'Application created successfully.',
        ));

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => ['id' => 1],
                'message' => 'Application created successfully.',
            ]);
    }

    public function test_no_content_returns_204_with_an_empty_body(): void
    {
        $controller = new TestAppApiController;

        $response = TestResponse::fromBaseResponse($controller->noContentResponse());

        $response->assertNoContent();
    }

    public function test_paginated_returns_the_standard_pagination_response(): void
    {
        $links = [
            'first' => 'https://application-tracker.test/api/v1/applications?page=1',
            'last' => 'https://application-tracker.test/api/v1/applications?page=1',
            'prev' => null,
            'next' => null,
        ];
        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => 1,
        ];
        $controller = new TestAppApiController;

        $response = TestResponse::fromBaseResponse($controller->paginatedResponse(
            [['id' => 1]],
            $links,
            $meta,
        ));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [['id' => 1]],
                'links' => $links,
                'meta' => $meta,
            ]);
    }

    public function test_file_returns_content_mime_type_and_unicode_content_disposition(): void
    {
        $controller = new TestAppApiController;

        $response = TestResponse::fromBaseResponse($controller->fileResponse(
            '%PDF prepared content',
            'Hồ sơ ứng tuyển.pdf',
            'application/pdf',
        ));

        $response
            ->assertOk()
            ->assertContent('%PDF prepared content')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeaderContains('Content-Disposition', 'attachment')
            ->assertHeaderContains('Content-Disposition', 'filename="Ho so ung tuyen.pdf"')
            ->assertHeaderContains(
                'Content-Disposition',
                "filename*=utf-8''H%E1%BB%93%20s%C6%A1%20%E1%BB%A9ng%20tuy%E1%BB%83n.pdf",
            );
    }

    public function test_file_supports_inline_content_disposition(): void
    {
        $controller = new TestAppApiController;

        $response = TestResponse::fromBaseResponse($controller->fileResponse(
            '%PDF prepared content',
            'cv.pdf',
            'application/pdf',
            HeaderUtils::DISPOSITION_INLINE,
        ));

        $response
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=cv.pdf');
    }
}

final class TestAppApiController extends AppApiController
{
    public function successResponse(mixed $data, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message);
    }

    public function createdResponse(mixed $data, ?string $message = null): JsonResponse
    {
        return $this->created($data, $message);
    }

    public function noContentResponse(): Response
    {
        return $this->noContent();
    }

    /**
     * @param  array{first: string|null, last: string|null, prev: string|null, next: string|null}  $links
     * @param  array{current_page: int, last_page: int, per_page: int, total: int}  $meta
     */
    public function paginatedResponse(
        array $data,
        array $links,
        array $meta,
        ?string $message = null,
    ): JsonResponse {
        return $this->paginated($data, $links, $meta, $message);
    }

    public function fileResponse(
        string $content,
        string $filename,
        string $mimeType,
        string $disposition = HeaderUtils::DISPOSITION_ATTACHMENT,
    ): Response {
        return $this->file($content, $filename, $mimeType, $disposition);
    }
}
