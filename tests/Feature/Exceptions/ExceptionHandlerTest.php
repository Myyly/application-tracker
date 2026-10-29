<?php

namespace Tests\Feature\Exceptions;

use App\Constants\ErrorCode;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthenticatedException;
use App\Exceptions\ValidationException as ApiValidationException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

class ExceptionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1', static function (): void {
            throw new ForbiddenException('You cannot access this CV.');
        });

        Route::get('/api/v1/test-exceptions/custom-not-found', static function (): void {
            throw new NotFoundException;
        });

        Route::get('/api/v1/test-exceptions/custom-unauthenticated', static function (): void {
            throw new UnauthenticatedException;
        });

        Route::get('/api/v1/test-exceptions/custom-validation', static function (): void {
            throw new ApiValidationException(errors: [
                'email' => ['The email field is required.'],
            ]);
        });

        Route::get('/api/v1/test-exceptions/laravel-validation', static function (): void {
            throw LaravelValidationException::withMessages([
                'email' => ['The email field is required.'],
            ]);
        });

        Route::get('/api/v1/test-exceptions/authentication', static function (): void {
            throw new AuthenticationException;
        });

        Route::get('/api/v1/test-exceptions/authorization', static function (): void {
            throw new AuthorizationException;
        });

        Route::get('/api/v1/test-exceptions/model-not-found', static function (): void {
            throw (new ModelNotFoundException)->setModel(User::class, 1);
        });

        Route::get('/api/v1/test-exceptions/method', static fn (): array => []);

        Route::get('/api/v1/test-exceptions/rate-limit', static function (): void {
            throw new ThrottleRequestsException(
                message: 'Sensitive throttle implementation detail.',
                headers: ['Retry-After' => '60'],
            );
        });

        Route::get('/api/v1/test-exceptions/http-error', static function (): void {
            throw new BadRequestHttpException('Sensitive bad request detail.');
        });

        Route::get('/api/v1/test-exceptions/unexpected', static function (): void {
            throw new RuntimeException('Sensitive database connection detail.');
        });

        Route::get('/test-web-exception', static function (): void {
            throw new RuntimeException('Web exception detail.');
        });
    }

    public function test_custom_forbidden_exception_returns_403_for_exact_api_v1_path(): void
    {
        $response = $this->getJson('/api/v1');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'You cannot access this CV.',
                'error_code' => ErrorCode::FORBIDDEN,
            ]);
    }

    public function test_custom_not_found_exception_returns_404(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/custom-not-found');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Not found',
                'error_code' => ErrorCode::NOT_FOUND,
            ]);
    }

    public function test_custom_unauthenticated_exception_returns_401(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/custom-unauthenticated');

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_custom_validation_exception_returns_422_with_errors(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/custom-validation');

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'error_code' => ErrorCode::VALIDATION_ERROR,
                'errors' => [
                    'email' => ['The email field is required.'],
                ],
            ]);
    }

    public function test_laravel_validation_exception_returns_422_with_errors(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/laravel-validation');

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Validation failed',
                'error_code' => ErrorCode::VALIDATION_ERROR,
                'errors' => [
                    'email' => ['The email field is required.'],
                ],
            ]);
    }

    public function test_laravel_authentication_exception_returns_401(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/authentication');

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_laravel_authorization_exception_returns_403(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/authorization');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'error_code' => ErrorCode::FORBIDDEN,
            ]);
    }

    public function test_model_not_found_exception_returns_404_without_exposing_model_details(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/model-not-found');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Not found',
                'error_code' => ErrorCode::NOT_FOUND,
            ]);
    }

    public function test_missing_api_route_returns_404(): void
    {
        $response = $this->getJson('/api/v1/route-that-does-not-exist');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Not found',
                'error_code' => ErrorCode::NOT_FOUND,
            ]);
    }

    public function test_unsupported_http_method_returns_405_and_preserves_allow_header(): void
    {
        $response = $this->postJson('/api/v1/test-exceptions/method');

        $response
            ->assertMethodNotAllowed()
            ->assertHeader('Allow', 'GET, HEAD')
            ->assertExactJson([
                'message' => 'Method not allowed',
                'error_code' => ErrorCode::METHOD_NOT_ALLOWED,
            ]);
    }

    public function test_rate_limit_exception_returns_429_and_preserves_retry_after_header(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/rate-limit');

        $response
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertExactJson([
                'message' => 'Too many requests',
                'error_code' => ErrorCode::TOO_MANY_REQUESTS,
            ]);
    }

    public function test_other_http_exception_preserves_its_status_without_exposing_its_message(): void
    {
        $response = $this->getJson('/api/v1/test-exceptions/http-error');

        $response
            ->assertBadRequest()
            ->assertExactJson([
                'message' => 'Bad Request',
                'error_code' => ErrorCode::HTTP_ERROR,
            ]);
    }

    public function test_unexpected_exception_returns_safe_500_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/api/v1/test-exceptions/unexpected');

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => ErrorCode::INTERNAL_SERVER_ERROR,
            ])
            ->assertDontSee('Sensitive database connection detail.')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('ExceptionHandlerTest.php');
    }

    public function test_web_route_keeps_laravel_default_html_exception_response(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/test-web-exception', ['Accept' => 'text/html']);

        $response
            ->assertInternalServerError()
            ->assertHeaderContains('Content-Type', 'text/html');
    }
}
