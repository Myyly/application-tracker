<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string ENDPOINT = '/api/v1/reset-password';

    private const string OLD_PASSWORD = 'OldPassword123!';

    private const string NEW_PASSWORD = 'NewPassword123!';

    public function test_resets_password_with_genuine_broker_token_without_authentication(): void
    {
        $user = $this->createUser();
        $oldHash = $user->getAuthPassword();
        $token = $this->issueResetToken($user);

        $response = $this->resetPassword($user->email, $token);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Password reset successfully',
            ]);
        $newHash = $user->fresh()->getAuthPassword();
        $this->assertNotSame($oldHash, $newHash);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $newHash));
    }

    public function test_incorrect_token_returns_unified_token_error(): void
    {
        $user = $this->createUser();
        $this->issueResetToken($user);

        $this->resetPassword($user->email, 'incorrect-token')
            ->assertUnprocessable()
            ->assertExactJson($this->invalidTokenResponse());
    }

    public function test_expired_token_returns_unified_token_error(): void
    {
        $this->travelTo('2026-11-01 12:00:00 UTC');
        $user = $this->createUser();
        $token = $this->issueResetToken($user);
        $this->travel(61)->minutes();

        $this->resetPassword($user->email, $token)
            ->assertUnprocessable()
            ->assertExactJson($this->invalidTokenResponse());
    }

    public function test_consumed_token_cannot_be_reused(): void
    {
        $user = $this->createUser();
        $token = $this->issueResetToken($user);

        $this->resetPassword($user->email, $token)->assertOk();
        $this->resetPassword($user->email, $token)
            ->assertUnprocessable()
            ->assertExactJson($this->invalidTokenResponse());
    }

    public function test_missing_user_returns_same_token_error(): void
    {
        $this->resetPassword('missing@example.com', 'unknown-token')
            ->assertUnprocessable()
            ->assertExactJson($this->invalidTokenResponse());
    }

    public function test_missing_required_fields_return_validation_error(): void
    {
        $this->postJson(self::ENDPOINT)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors([
                'email',
                'token',
                'password',
                'password_confirmation',
            ]);
    }

    public function test_invalid_email_returns_validation_error(): void
    {
        $payload = $this->validPayload('not-an-email', 'token');

        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_shorter_than_eight_characters_is_rejected(): void
    {
        $payload = $this->validPayload('user@example.com', 'token', 'Short1!');

        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The password field must be at least 8 characters.')
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_over_seventy_two_bytes_is_rejected(): void
    {
        $password = str_repeat('a', 73);
        $payload = $this->validPayload('user@example.com', 'token', $password);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.password.0',
                'The password field must not be greater than 72 bytes.',
            )
            ->assertJsonValidationErrors(['password']);
    }

    public function test_multibyte_password_over_seventy_two_bytes_is_rejected(): void
    {
        $password = str_repeat('🙂', 19);
        $payload = $this->validPayload('user@example.com', 'token', $password);

        $this->assertSame(19, mb_strlen($password));
        $this->assertSame(76, strlen($password));
        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.password.0',
                'The password field must not be greater than 72 bytes.',
            )
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $payload = $this->validPayload('user@example.com', 'token');
        $payload['password_confirmation'] = 'DifferentPassword123!';

        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The password field confirmation does not match.')
            ->assertJsonValidationErrors(['password']);
    }

    public function test_sixth_request_from_same_ip_is_rate_limited(): void
    {
        $this->travelTo('2026-11-01 12:00:00 UTC');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(
                self::ENDPOINT,
                $this->validPayload('missing@example.com', "invalid-token-{$attempt}"),
            )->assertUnprocessable();
        }

        $this->postJson(
            self::ENDPOINT,
            $this->validPayload('missing@example.com', 'invalid-token-6'),
        )
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertExactJson([
                'message' => 'Too many requests',
                'error_code' => ErrorCode::TOO_MANY_REQUESTS,
            ]);
    }

    public function test_different_ips_have_independent_rate_limits(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
                ->postJson(
                    self::ENDPOINT,
                    $this->validPayload('missing@example.com', "invalid-token-{$attempt}"),
                )
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->postJson(
                self::ENDPOINT,
                $this->validPayload('missing@example.com', 'invalid-token-6'),
            )
            ->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21'])
            ->postJson(
                self::ENDPOINT,
                $this->validPayload('missing@example.com', 'invalid-token-other-ip'),
            )
            ->assertUnprocessable()
            ->assertExactJson($this->invalidTokenResponse());
    }

    public function test_old_login_fails_and_new_login_succeeds_after_reset(): void
    {
        $user = $this->createUser();
        $token = $this->issueResetToken($user);

        $this->resetPassword($user->email, $token)->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => self::OLD_PASSWORD,
        ])->assertUnauthorized();
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
        ])->assertOk();
    }

    public function test_reset_revokes_only_reset_users_existing_sanctum_tokens(): void
    {
        $userA = $this->createUser('reset.user-a@example.com');
        $userB = $this->createUser('reset.user-b@example.com');
        $tokenA1 = $userA->createToken('device-a1')->plainTextToken;
        $tokenA2 = $userA->createToken('device-a2')->plainTextToken;
        $tokenB = $userB->createToken('device-b')->plainTextToken;
        $resetToken = $this->issueResetToken($userA);

        $this->resetPassword($userA->email, $resetToken)->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $userA->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $userB->getKey()]);
        foreach ([$tokenA1, $tokenA2] as $revokedToken) {
            Auth::forgetGuards();
            $this->getProfile($revokedToken)->assertUnauthorized();
        }
        Auth::forgetGuards();
        $this->getProfile($tokenB)
            ->assertOk()
            ->assertJsonPath('data.email', $userB->email);
    }

    public function test_invalid_token_preserves_password_access_tokens_and_reset_token(): void
    {
        $user = $this->createUser();
        $originalHash = $user->getAuthPassword();
        $accessToken = $user->createToken('existing-device');
        $this->issueResetToken($user);
        Event::fake([PasswordReset::class]);

        $this->resetPassword($user->email, 'incorrect-token')->assertUnprocessable();

        $this->assertSame($originalHash, $user->fresh()->getAuthPassword());
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $accessToken->accessToken->getKey(),
        ]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_unverified_user_remains_unverified_after_reset(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => self::OLD_PASSWORD,
        ]);
        $token = $this->issueResetToken($user);

        $this->resetPassword($user->email, $token)->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->getAuthPassword()));
    }

    public function test_success_consumes_reset_token_and_dispatches_event_after_commit(): void
    {
        $user = $this->createUser();
        $token = $this->issueResetToken($user);
        Event::fake([PasswordReset::class]);

        $this->resetPassword($user->email, $token)->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Event::assertDispatched(
            PasswordReset::class,
            fn (PasswordReset $event): bool => $event->user->is($user),
        );
    }

    public function test_database_failure_rolls_back_password_tokens_and_reset_token(): void
    {
        $user = $this->createUser();
        $originalHash = $user->getAuthPassword();
        $accessToken = $user->createToken('existing-device');
        $token = $this->issueResetToken($user);
        Event::fake([PasswordReset::class]);
        User::saving(
            fn (User $savingUser): bool => ! $savingUser->isDirty('password'),
        );

        try {
            $response = $this->resetPassword($user->email, $token);
        } finally {
            User::flushEventListeners();
        }

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => ErrorCode::INTERNAL_SERVER_ERROR,
            ]);
        $this->assertSame($originalHash, $user->fresh()->getAuthPassword());
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $accessToken->accessToken->getKey(),
        ]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_success_response_does_not_expose_sensitive_data(): void
    {
        $user = $this->createUser();
        $token = $this->issueResetToken($user);

        $this->resetPassword($user->email, $token)
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Password reset successfully',
            ])
            ->assertDontSee($token)
            ->assertDontSee(self::NEW_PASSWORD)
            ->assertDontSee($user->fresh()->getAuthPassword());
    }

    private function createUser(string $email = 'reset.user@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => self::OLD_PASSWORD,
        ]);
    }

    private function issueResetToken(User $user): string
    {
        Notification::fake();
        $token = null;

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
            ->assertOk();
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertIsString($token);

        return $token;
    }

    private function resetPassword(
        string $email,
        string $token,
        string $password = self::NEW_PASSWORD,
    ): TestResponse {
        return $this->postJson(
            self::ENDPOINT,
            $this->validPayload($email, $token, $password),
        );
    }

    /**
     * @return array{email: string, token: string, password: string, password_confirmation: string}
     */
    private function validPayload(string $email, string $token, string $password = self::NEW_PASSWORD): array
    {
        return [
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    private function getProfile(string $accessToken): TestResponse
    {
        return $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
    }

    /**
     * @return array{message: string, error_code: string, errors: array{token: list<string>}}
     */
    private function invalidTokenResponse(): array
    {
        return [
            'message' => 'Invalid or expired reset token',
            'error_code' => ErrorCode::INVALID_RESET_TOKEN,
            'errors' => [
                'token' => ['The reset token is invalid or expired.'],
            ],
        ];
    }
}
