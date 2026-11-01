<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string ENDPOINT = '/api/v1/forgot-password';

    private const string SUCCESS_MESSAGE = 'If an account exists for this email, a password reset link will be sent.';

    public function test_existing_email_receives_reset_notification_without_authentication(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->forgotPassword($user->email);

        $response
            ->assertOk()
            ->assertExactJson($this->successResponse());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_returns_approved_not_found_contract(): void
    {
        Notification::fake();

        $this->forgotPassword('missing@example.com')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Email not found',
                'error_code' => ErrorCode::EMAIL_NOT_FOUND,
                'errors' => [
                    'email' => ['This email is not registered.'],
                ],
            ]);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_missing_email_returns_validation_error(): void
    {
        $this->postJson(self::ENDPOINT)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.email.0', 'The email field is required.')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invalid_email_returns_validation_error(): void
    {
        $this->forgotPassword('not-an-email')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_sixth_request_from_same_ip_is_rate_limited(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-01 12:00:00 UTC');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->forgotPassword("missing{$attempt}@example.com")
                ->assertUnprocessable();
        }

        $this->forgotPassword('missing6@example.com')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertExactJson([
                'message' => 'Too many requests',
                'error_code' => ErrorCode::TOO_MANY_REQUESTS,
            ]);
        Notification::assertNothingSent();
    }

    public function test_different_ips_have_independent_api_rate_limits(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson(self::ENDPOINT, ['email' => "first{$attempt}@example.com"])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson(self::ENDPOINT, ['email' => 'first6@example.com'])
            ->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->postJson(self::ENDPOINT, ['email' => 'second@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::EMAIL_NOT_FOUND);
        Notification::assertNothingSent();
    }

    public function test_broker_throttle_returns_generic_success_without_another_notification(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-01 12:00:00 UTC');
        $user = User::factory()->create();

        $this->forgotPassword($user->email)->assertOk();
        $this->forgotPassword($user->email)
            ->assertOk()
            ->assertExactJson($this->successResponse());

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_unverified_user_can_request_reset_without_becoming_verified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->forgotPassword($user->email)->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_broker_persists_only_hashed_token_with_approved_configuration(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $plainToken = null;

        $this->forgotPassword($user->email)->assertOk();
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$plainToken): bool {
                $plainToken = $notification->token;

                return true;
            },
        );

        $storedToken = (string) $this->app['db']
            ->table('password_reset_tokens')
            ->where('email', $user->email)
            ->value('token');

        $this->assertIsString($plainToken);
        $this->assertNotSame($plainToken, $storedToken);
        $this->assertTrue(Hash::check($plainToken, $storedToken));
        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->assertSame(60, config('auth.passwords.users.throttle'));
    }

    public function test_forgot_password_preserves_password_verification_and_access_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $originalPassword = $user->getAuthPassword();
        $originalVerifiedAt = $user->email_verified_at?->toDateTimeString();
        $accessToken = $user->createToken('existing-session');

        $this->forgotPassword($user->email)->assertOk();

        $freshUser = $user->fresh();
        $this->assertSame($originalPassword, $freshUser->getAuthPassword());
        $this->assertSame($originalVerifiedAt, $freshUser->email_verified_at?->toDateTimeString());
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $accessToken->accessToken->getKey(),
            'tokenable_id' => $user->getKey(),
        ]);
    }

    public function test_reset_notification_uses_encoded_frontend_url(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://frontend.example/']);
        $user = User::factory()->create([
            'email' => 'reset+tag@example.com',
        ]);

        $this->forgotPassword($user->email)->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $expectedUrl = 'https://frontend.example/reset-password?'.http_build_query([
                    'token' => $notification->token,
                    'email' => $user->email,
                ], encoding_type: PHP_QUERY_RFC3986);

                return $notification->toMail($user)->actionUrl === $expectedUrl;
            },
        );
    }

    public function test_mail_transport_failure_returns_safe_server_error(): void
    {
        $user = User::factory()->create();
        ResetPassword::toMailUsing(
            static fn (): never => throw new RuntimeException('Sensitive mail transport failure.'),
        );

        try {
            $response = $this->forgotPassword($user->email);
        } finally {
            ResetPassword::$toMailCallback = null;
        }

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => ErrorCode::INTERNAL_SERVER_ERROR,
            ])
            ->assertDontSee('Sensitive mail transport failure');
        $this->assertSame($user->getAuthPassword(), $user->fresh()->getAuthPassword());
    }

    public function test_success_response_does_not_expose_token_or_user_data(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->forgotPassword($user->email)
            ->assertOk()
            ->assertExactJson($this->successResponse())
            ->assertDontSee($user->email)
            ->assertDontSee($user->getAuthPassword());
    }

    private function forgotPassword(string $email): TestResponse
    {
        return $this->postJson(self::ENDPOINT, ['email' => $email]);
    }

    /**
     * @return array{data: null, message: string}
     */
    private function successResponse(): array
    {
        return [
            'data' => null,
            'message' => self::SUCCESS_MESSAGE,
        ];
    }
}
