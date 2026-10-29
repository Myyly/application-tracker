<?php

namespace Tests\Feature\Http\Responses;

use App\Http\Responses\ApiResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_returns_200_with_data_and_message(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::success(
            ['id' => 1, 'company_name' => 'ABC Technology'],
            'Success.',
        ));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => ['id' => 1, 'company_name' => 'ABC Technology'],
                'message' => 'Success.',
            ]);
    }

    public function test_created_returns_201_with_data_and_message(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::created(
            ['id' => 1, 'company_name' => 'ABC Technology'],
            'Application created successfully.',
        ));

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => ['id' => 1, 'company_name' => 'ABC Technology'],
                'message' => 'Application created successfully.',
            ]);
    }

    public function test_no_content_returns_204_with_an_empty_body(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::noContent());

        $response->assertNoContent();
    }

    public function test_success_omits_message_when_not_supplied(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::success(['id' => 1]));

        $response
            ->assertOk()
            ->assertExactJson(['data' => ['id' => 1]]);
    }

    public function test_success_preserves_an_empty_array(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::success([]));

        $response
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_success_preserves_null(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::success(null));

        $response
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_success_preserves_a_scalar_value(): void
    {
        $response = TestResponse::fromBaseResponse(ApiResponse::success(0));

        $response
            ->assertOk()
            ->assertExactJson(['data' => 0]);
    }

    public function test_paginated_returns_data_links_and_meta_without_extra_fields(): void
    {
        $links = [
            'first' => 'https://application-tracker.test/api/applications?page=1',
            'last' => 'https://application-tracker.test/api/applications?page=1',
            'prev' => null,
            'next' => null,
        ];
        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => 0,
        ];

        $response = TestResponse::fromBaseResponse(ApiResponse::paginated([], $links, $meta));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'links' => $links,
                'meta' => $meta,
            ]);
    }

    public function test_paginated_includes_message_when_supplied(): void
    {
        $links = [
            'first' => 'https://application-tracker.test/api/applications?page=1',
            'last' => 'https://application-tracker.test/api/applications?page=1',
            'prev' => null,
            'next' => null,
        ];
        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => 1,
        ];

        $response = TestResponse::fromBaseResponse(ApiResponse::paginated(
            [['id' => 1]],
            $links,
            $meta,
            'Applications retrieved successfully.',
        ));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [['id' => 1]],
                'links' => $links,
                'meta' => $meta,
                'message' => 'Applications retrieved successfully.',
            ]);
    }
}
