<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class ResendEmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string ENDPOINT = '/api/v1/email/verification/resend';

    public function test_unverified_user_receives_verification_notification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->resend($this->issueToken($user));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Verification email sent successfully',
            ]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_receives_success_without_notification_and_is_rate_limited(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-01 12:00:00 UTC');
        $user = User::factory()->create([
            'email_verified_at' => '2026-10-30 08:00:00',
        ]);
        $originalVerifiedAt = $user->email_verified_at->toDateTimeString();
        $accessToken = $this->issueToken($user);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->resend($accessToken)
                ->assertOk()
                ->assertExactJson([
                    'data' => null,
                    'message' => 'Email already verified',
                ]);
        }

        $this->resend($accessToken)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '900');
        $this->assertSame(
            $originalVerifiedAt,
            $user->fresh()->email_verified_at->toDateTimeString(),
        );
        Notification::assertNothingSent();
    }

    public function test_rejects_request_without_bearer_token(): void
    {
        Notification::fake();

        $this->postJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertExactJson($this->unauthenticatedResponse());
        Notification::assertNothingSent();
    }

    public function test_rejects_invalid_bearer_token(): void
    {
        Notification::fake();

        $this->resend('invalid-token')
            ->assertUnauthorized()
            ->assertExactJson($this->unauthenticatedResponse());
        Notification::assertNothingSent();
    }

    public function test_rejects_revoked_bearer_token(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('verification-resend-test');
        $accessToken = $token->plainTextToken;
        $token->accessToken->delete();

        $this->resend($accessToken)
            ->assertUnauthorized()
            ->assertExactJson($this->unauthenticatedResponse());
        Notification::assertNothingSent();
    }

    public function test_allows_three_requests_within_fifteen_minutes(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-01 12:00:00 UTC');
        $user = User::factory()->unverified()->create();
        $accessToken = $this->issueToken($user);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->resend($accessToken)->assertOk();
        }

        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_fourth_request_is_throttled_without_sending_another_notification(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-01 12:00:00 UTC');
        $user = User::factory()->unverified()->create();
        $accessToken = $this->issueToken($user);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->resend($accessToken)->assertOk();
        }

        $this->resend($accessToken)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '900')
            ->assertExactJson([
                'message' => 'Too many requests',
                'error_code' => ErrorCode::TOO_MANY_REQUESTS,
            ]);
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_users_have_independent_rate_limit_buckets(): void
    {
        Notification::fake();
        $userA = User::factory()->unverified()->create();
        $userB = User::factory()->unverified()->create();
        $tokenA = $this->issueToken($userA);
        $tokenB = $this->issueToken($userB);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->resend($tokenA)->assertOk();
        }

        Auth::forgetGuards();
        $this->resend($tokenB)->assertOk();
        Auth::forgetGuards();
        $this->resend($tokenA)->assertTooManyRequests();
        Auth::forgetGuards();
        $this->resend($tokenB)->assertOk();
        Notification::assertSentToTimes($userA, VerifyEmail::class, 3);
        Notification::assertSentToTimes($userB, VerifyEmail::class, 2);
    }

    public function test_notification_is_sent_only_to_authenticated_user(): void
    {
        Notification::fake();
        $authenticatedUser = User::factory()->unverified()->create();
        $otherUser = User::factory()->unverified()->create();

        $this->postJson(self::ENDPOINT, [
            'email' => $otherUser->email,
            'user_id' => $otherUser->getKey(),
        ], [
            'Authorization' => 'Bearer '.$this->issueToken($authenticatedUser),
        ])->assertOk();

        Notification::assertSentTo($authenticatedUser, VerifyEmail::class);
        Notification::assertNotSentTo($otherUser, VerifyEmail::class);
    }

    public function test_resent_notification_contains_valid_signed_callback_url(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->resend($this->issueToken($user))->assertOk();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            function (VerifyEmail $notification, array $channels) use ($user): bool {
                $verificationRequest = Request::create(
                    $notification->toMail($user)->actionUrl,
                );

                return $channels === ['mail']
                    && str_starts_with($verificationRequest->path(), 'api/v1/email/verify/')
                    && URL::hasValidSignature($verificationRequest);
            },
        );
    }

    public function test_resent_link_verifies_email_through_existing_callback(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $verificationUrl = null;

        $this->resend($this->issueToken($user))->assertOk();
        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            function (VerifyEmail $notification) use ($user, &$verificationUrl): bool {
                $verificationUrl = $notification->toMail($user)->actionUrl;

                return true;
            },
        );

        $this->assertIsString($verificationUrl);
        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Email verified successfully',
            ]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_does_not_verify_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->resend($this->issueToken($user))->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_mail_delivery_failure_returns_server_error_instead_of_success(): void
    {
        $user = User::factory()->unverified()->create();
        VerifyEmail::toMailUsing(
            static fn (): never => throw new RuntimeException('Sensitive mail transport failure.'),
        );

        try {
            $response = $this->resend($this->issueToken($user));
        } finally {
            VerifyEmail::$toMailCallback = null;
        }

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => ErrorCode::INTERNAL_SERVER_ERROR,
            ])
            ->assertDontSee('Sensitive mail transport failure');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_response_does_not_expose_signed_url_token_or_user_data(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $accessToken = $this->issueToken($user);

        $this->resend($accessToken)
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Verification email sent successfully',
            ])
            ->assertDontSee($accessToken)
            ->assertDontSee($user->email);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('verification-resend-test')->plainTextToken;
    }

    private function resend(string $accessToken): TestResponse
    {
        return $this->postJson(self::ENDPOINT, headers: [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
    }

    /**
     * @return array{message: string, error_code: string}
     */
    private function unauthenticatedResponse(): array
    {
        return [
            'message' => 'Unauthenticated',
            'error_code' => ErrorCode::UNAUTHORIZED,
        ];
    }
}
