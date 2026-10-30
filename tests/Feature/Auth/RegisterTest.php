<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registers_unverified_user_without_login_or_access_token(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => 1,
                    'name' => 'Test Register',
                    'email' => 'register.test@example.com',
                    'emailVerifiedAt' => null,
                ],
                'message' => 'User registered successfully',
            ]);

        $user = User::query()->where('email', 'register.test@example.com')->firstOrFail();

        $this->assertSame('Test Register', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertNotSame('StrongPassword123!', $user->password);
        $this->assertGuest();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_dispatches_registered_event_after_user_is_persisted(): void
    {
        Event::fake([Registered::class]);

        $this->postJson('/api/v1/register', $this->validPayload())
            ->assertCreated();

        Event::assertDispatched(
            Registered::class,
            fn (Registered $event): bool => $event->user->exists
                && $event->user->email === 'register.test@example.com',
        );
    }

    public function test_registered_event_listener_sends_verification_notification_with_valid_signed_url(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/register', $this->validPayload())
            ->assertCreated();

        $user = User::query()->where('email', 'register.test@example.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            function (VerifyEmail $notification, array $channels) use ($user): bool {
                $mailMessage = $notification->toMail($user);
                $verificationRequest = Request::create($mailMessage->actionUrl);

                return $channels === ['mail']
                    && str_starts_with($verificationRequest->path(), 'api/v1/email/verify/')
                    && URL::hasValidSignature($verificationRequest);
            },
        );
    }

    public function test_rejects_duplicate_email_with_validation_contract(): void
    {
        User::factory()->create([
            'email' => 'register.test@example.com',
        ]);

        $response = $this->postJson('/api/v1/register', $this->validPayload());

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.email.0', 'The email has already been taken.')
            ->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_rejects_invalid_email_with_validation_contract(): void
    {
        $payload = $this->validPayload();
        $payload['email'] = 'not-an-email';

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.')
            ->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_missing_required_fields_with_validation_contract(): void
    {
        $response = $this->postJson('/api/v1/register');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.name.0', 'The name field is required.')
            ->assertJsonPath('errors.email.0', 'The email field is required.')
            ->assertJsonPath('errors.password.0', 'The password field is required.')
            ->assertJsonPath(
                'errors.password_confirmation.0',
                'The password confirmation field is required.',
            )
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'password_confirmation',
            ]);
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('invalidPasswordLengthProvider')]
    public function test_rejects_password_outside_length_bounds(
        string $password,
        string $expectedMessage,
    ): void {
        $payload = $this->validPayload();
        $payload['password'] = $password;
        $payload['password_confirmation'] = $password;

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.password.0', $expectedMessage)
            ->assertJsonValidationErrors(['password']);
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('validPasswordLengthProvider')]
    public function test_accepts_password_within_length_bounds(
        string $email,
        string $password,
    ): void {
        Event::fake([Registered::class]);
        $payload = $this->validPayload();
        $payload['email'] = $email;
        $payload['password'] = $password;
        $payload['password_confirmation'] = $password;

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', $email)
            ->assertJsonMissingPath('data.password');

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotSame($password, $user->password);
    }

    public function test_rejects_unicode_password_over_72_bytes(): void
    {
        $password = str_repeat('🙂', 19);
        $payload = $this->validPayload();
        $payload['password'] = $password;
        $payload['password_confirmation'] = $password;

        $this->assertSame(19, mb_strlen($password));
        $this->assertSame(76, strlen($password));

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath(
                'errors.password.0',
                'The password field must not be greater than 72 bytes.',
            )
            ->assertJsonValidationErrors(['password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_missing_password_confirmation(): void
    {
        $payload = $this->validPayload();
        unset($payload['password_confirmation']);

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath(
                'errors.password_confirmation.0',
                'The password confirmation field is required.',
            )
            ->assertJsonValidationErrors(['password_confirmation']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_mismatched_password_confirmation(): void
    {
        $payload = $this->validPayload();
        $payload['password_confirmation'] = 'DifferentPassword123!';

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath(
                'errors.password.0',
                'The password field confirmation does not match.',
            )
            ->assertJsonValidationErrors(['password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_ignores_internal_fields_and_does_not_expose_sensitive_data(): void
    {
        Event::fake([Registered::class]);
        $payload = [
            ...$this->validPayload(),
            'id' => 999,
            'email_verified_at' => '2026-10-30 08:00:00',
            'avatar_path' => 'avatars/internal.jpg',
            'access_token' => 'client-controlled-token',
            'role' => 'admin',
            'created_at' => '2026-10-30 08:00:00',
            'updated_at' => '2026-10-30 08:00:00',
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => 1,
                    'name' => 'Test Register',
                    'email' => 'register.test@example.com',
                    'emailVerifiedAt' => null,
                ],
                'message' => 'User registered successfully',
            ]);

        $user = User::query()->where('email', 'register.test@example.com')->firstOrFail();

        $this->assertSame(1, $user->getKey());
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->avatar_path);
        $this->assertFalse(property_exists($user, 'role'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public static function invalidPasswordLengthProvider(): array
    {
        return [
            'shorter than 8 characters' => [
                str_repeat('a', 7),
                'The password field must be at least 8 characters.',
            ],
            'longer than 72 bytes' => [
                str_repeat('a', 73),
                'The password field must not be greater than 72 bytes.',
            ],
        ];
    }

    public static function validPasswordLengthProvider(): array
    {
        return [
            'exactly 8 characters' => [
                'password.8@example.com',
                str_repeat('a', 8),
            ],
            'exactly 11 characters' => [
                'password.11@example.com',
                str_repeat('a', 11),
            ],
            'exactly 72 bytes' => [
                'password.72@example.com',
                str_repeat('a', 72),
            ],
        ];
    }

    private function validPayload(): array
    {
        return [
            'name' => 'Test Register',
            'email' => 'register.test@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ];
    }
}
