<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PASSWORD = 'LoginPassword123!';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/test-authenticated-user', static function (Request $request): array {
            return ['id' => $request->user()->getAuthIdentifier()];
        })->middleware('auth:sanctum');
    }

    public function test_logs_in_with_valid_credentials_and_returns_only_token_data(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00 UTC'));
        $user = $this->createUser('login.success@example.com');

        $response = $this->postJson('/api/v1/login', $this->validPayload($user->email));

        $response
            ->assertOk()
            ->assertJsonPath('data.tokenType', 'Bearer')
            ->assertJsonPath('data.expiresAt', '2026-11-06T12:00:00Z')
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPaths([
                'data.user',
                'data.id',
                'data.name',
                'data.email',
                'data.password',
                'data.password_hash',
            ]);

        $accessToken = $response->json('data.accessToken');
        $token = $user->tokens()->sole();
        [$tokenId, $plainTextToken] = explode('|', $accessToken, 2);

        $this->assertIsString($accessToken);
        $this->assertSame($token->getKey(), (int) $tokenId);
        $this->assertSame(hash('sha256', $plainTextToken), $token->token);
        $this->assertNotSame($accessToken, $token->token);
        $this->assertSame('2026-11-06 12:00:00', $token->expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_rejects_unknown_email_without_revealing_account_existence(): void
    {
        $response = $this->postJson('/api/v1/login', $this->validPayload('missing@example.com'));

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid email or password.',
                'error_code' => ErrorCode::INVALID_CREDENTIALS,
            ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rejects_wrong_password_with_same_generic_error(): void
    {
        $user = $this->createUser('login.wrong-password@example.com');
        $payload = $this->validPayload($user->email);
        $payload['password'] = 'WrongPassword!';

        $response = $this->postJson('/api/v1/login', $payload);

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid email or password.',
                'error_code' => ErrorCode::INVALID_CREDENTIALS,
            ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rejects_missing_credentials_with_validation_contract(): void
    {
        $response = $this->postJson('/api/v1/login');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.email.0', 'The email field is required.')
            ->assertJsonPath('errors.password.0', 'The password field is required.')
            ->assertJsonValidationErrors(['email', 'password']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rejects_invalid_email_with_validation_contract(): void
    {
        $response = $this->postJson('/api/v1/login', $this->validPayload('not-an-email'));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.')
            ->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rejects_non_string_password_with_validation_contract(): void
    {
        $payload = $this->validPayload('login.invalid-password-type@example.com');
        $payload['password'] = ['not', 'a', 'string'];

        $response = $this->postJson('/api/v1/login', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.password.0', 'The password field must be a string.')
            ->assertJsonValidationErrors(['password']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_allows_unverified_user_to_login(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'login.unverified@example.com',
            'password' => self::PASSWORD,
        ]);

        $response = $this->postJson('/api/v1/login', $this->validPayload($user->email));

        $response
            ->assertOk()
            ->assertJsonPath('data.tokenType', 'Bearer');
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_does_not_apply_register_password_length_rules(): void
    {
        $password = 'short';
        $user = User::factory()->create([
            'email' => 'login.short-password@example.com',
            'password' => $password,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_token_expires_after_exactly_seven_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00 UTC'));
        $user = $this->createUser('login.expiration@example.com');
        $loginResponse = $this->postJson('/api/v1/login', $this->validPayload($user->email));
        $accessToken = $loginResponse->json('data.accessToken');

        $this->getJson('/api/v1/test-authenticated-user', [
            'Authorization' => 'Bearer '.$accessToken,
        ])->assertOk();

        Auth::forgetGuards();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:01 UTC'));

        $this->getJson('/api/v1/test-authenticated-user', [
            'Authorization' => 'Bearer '.$accessToken,
        ])->assertUnauthorized()->assertExactJson([
            'message' => 'Unauthenticated',
            'error_code' => ErrorCode::UNAUTHORIZED,
        ]);
    }

    public function test_two_logins_create_independent_tokens_that_remain_valid(): void
    {
        $user = $this->createUser('login.multi-device@example.com');

        $firstToken = $this->postJson('/api/v1/login', $this->validPayload($user->email))
            ->assertOk()
            ->json('data.accessToken');
        $secondToken = $this->postJson('/api/v1/login', $this->validPayload($user->email))
            ->assertOk()
            ->json('data.accessToken');

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertDatabaseCount('personal_access_tokens', 2);

        foreach ([$firstToken, $secondToken] as $accessToken) {
            $this->getJson('/api/v1/test-authenticated-user', [
                'Authorization' => 'Bearer '.$accessToken,
            ])->assertOk()->assertJsonPath('id', $user->getKey());
        }
    }

    public function test_sixth_login_attempt_for_same_ip_and_email_is_rate_limited(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00 UTC'));
        $user = $this->createUser('login.rate-limit@example.com');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/login', $this->validPayload($user->email))
                ->assertOk();
        }

        $this->postJson('/api/v1/login', $this->validPayload($user->email))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertExactJson([
                'message' => 'Too many requests',
                'error_code' => ErrorCode::TOO_MANY_REQUESTS,
            ]);
        $this->assertDatabaseCount('personal_access_tokens', 5);
    }

    public function test_rate_limit_uses_normalized_email_and_client_ip_buckets(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00 UTC'));
        $email = 'login.bucket@example.com';

        foreach ([$email, strtoupper($email), '  '.$email.'  ', $email, strtoupper($email)] as $attemptEmail) {
            $status = $this->postJson('/api/v1/login', $this->validPayload($attemptEmail))->status();

            $this->assertContains($status, [401, 422]);
        }

        $this->postJson('/api/v1/login', $this->validPayload($email))
            ->assertTooManyRequests();

        $this->postJson('/api/v1/login', $this->validPayload('login.other-bucket@example.com'))
            ->assertUnauthorized();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/v1/login', $this->validPayload($email))
            ->assertUnauthorized();
    }

    public function test_rate_limit_resets_after_sixty_seconds_without_sleeping(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00 UTC'));
        $email = 'login.rate-reset@example.com';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/login', $this->validPayload($email))
                ->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', $this->validPayload($email))
            ->assertTooManyRequests();

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/login', $this->validPayload($email))
            ->assertUnauthorized();
    }

    private function createUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
    }

    /**
     * @return array{email: string, password: string}
     */
    private function validPayload(string $email): array
    {
        return [
            'email' => $email,
            'password' => self::PASSWORD,
        ];
    }
}
