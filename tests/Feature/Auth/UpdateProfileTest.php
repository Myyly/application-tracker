<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updates_name_successfully(): void
    {
        $user = $this->createUser(['name' => 'Old Name']);

        $response = $this->patchProfile($user, ['name' => 'Nguyen Van A']);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Nguyen Van A')
            ->assertJsonMissingPath('data.id');
        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Nguyen Van A',
        ]);
    }

    public function test_updates_multiple_profile_fields(): void
    {
        $user = $this->createUser();

        $response = $this->patchProfile($user, [
            'name' => 'Nguyen Van A',
            'dateOfBirth' => '2000-05-20',
            'gender' => USER_GENDER_MALE,
            'phone' => '+84901234567',
        ]);

        $response->assertOk()->assertExactJson([
            'data' => [
                'name' => 'Nguyen Van A',
                'email' => $user->email,
                'dateOfBirth' => '2000-05-20',
                'gender' => USER_GENDER_MALE,
                'phone' => '+84901234567',
                'avatarUrl' => null,
                'emailVerifiedAt' => $user->email_verified_at?->toAtomString(),
            ],
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Nguyen Van A',
            'phone' => '+84901234567',
        ]);
        $user->refresh();
        $this->assertSame('2000-05-20', $user->date_of_birth?->toDateString());
        $this->assertSame(USER_GENDER_MALE, $user->gender);
    }

    public function test_omitted_fields_remain_unchanged(): void
    {
        $user = $this->createUser([
            'name' => 'Original Name',
            'date_of_birth' => '1999-01-02',
            'gender' => USER_GENDER_FEMALE,
            'phone' => '0901234567',
        ]);

        $this->patchProfile($user, ['name' => 'Updated Name'])->assertOk();

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('1999-01-02', $user->date_of_birth?->toDateString());
        $this->assertSame(USER_GENDER_FEMALE, $user->gender);
        $this->assertSame('0901234567', $user->phone);
    }

    public function test_explicit_null_clears_phone(): void
    {
        $user = $this->createUser(['phone' => '0901234567']);

        $this->patchProfile($user, ['phone' => null])
            ->assertOk()
            ->assertJsonPath('data.phone', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'phone' => null,
        ]);
    }

    public function test_explicit_null_clears_date_of_birth_and_gender(): void
    {
        $user = $this->createUser([
            'date_of_birth' => '2000-05-20',
            'gender' => USER_GENDER_MALE,
        ]);

        $this->patchProfile($user, [
            'dateOfBirth' => null,
            'gender' => null,
        ])->assertOk()
            ->assertJsonPath('data.dateOfBirth', null)
            ->assertJsonPath('data.gender', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'date_of_birth' => null,
            'gender' => null,
        ]);
    }

    public function test_rejects_empty_payload(): void
    {
        $user = $this->createUser();

        $this->patchProfile($user, [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.profile.0', 'At least one profile field must be provided.');
    }

    public function test_rejects_payload_containing_only_forbidden_fields(): void
    {
        $user = $this->createUser(['email' => 'unchanged@example.com']);

        $this->patchProfile($user, [
            'email' => 'attacker@example.com',
            'password' => 'InjectedPassword123!',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors('profile');

        $this->assertSame('unchanged@example.com', $user->fresh()?->email);
    }

    #[DataProvider('invalidNameProvider')]
    public function test_rejects_invalid_name(mixed $name): void
    {
        $user = $this->createUser(['name' => 'Original Name']);

        $this->patchProfile($user, ['name' => $name])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors('name');

        $this->assertSame('Original Name', $user->fresh()?->name);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidNameProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace only' => ['   '],
        ];
    }

    #[DataProvider('invalidDateOfBirthProvider')]
    public function test_rejects_invalid_or_future_date_of_birth(string $dateOfBirth): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00 UTC'));
        $user = $this->createUser(['date_of_birth' => '2000-01-01']);

        $this->patchProfile($user, ['dateOfBirth' => $dateOfBirth])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors('dateOfBirth');

        $this->assertSame('2000-01-01', $user->fresh()?->date_of_birth?->toDateString());
    }

    /** @return array<string, array{string}> */
    public static function invalidDateOfBirthProvider(): array
    {
        return [
            'invalid calendar date' => ['2026-02-30'],
            'wrong format' => ['31-10-2000'],
            'future date' => ['2026-11-01'],
        ];
    }

    #[DataProvider('invalidGenderProvider')]
    public function test_rejects_gender_outside_allowed_values(int $gender): void
    {
        $user = $this->createUser(['gender' => USER_GENDER_MALE]);

        $this->patchProfile($user, ['gender' => $gender])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors('gender');

        $this->assertSame(USER_GENDER_MALE, $user->fresh()?->gender);
    }

    /** @return array<string, array{int}> */
    public static function invalidGenderProvider(): array
    {
        return [
            'below range' => [-1],
            'above range' => [2],
        ];
    }

    #[DataProvider('validPhoneProvider')]
    public function test_accepts_supported_phone_formats(string $phone): void
    {
        $user = $this->createUser(['phone' => null]);

        $this->patchProfile($user, ['phone' => $phone])
            ->assertOk()
            ->assertJsonPath('data.phone', $phone);

        $this->assertSame($phone, $user->fresh()?->phone);
    }

    /** @return array<string, array{string}> */
    public static function validPhoneProvider(): array
    {
        return [
            'Vietnam local' => ['0901234567'],
            'Vietnam international' => ['+84901234567'],
            'other international' => ['+12025550123'],
            'minimum length' => ['1234567'],
            'maximum length' => ['123456789012345'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_rejects_invalid_phone_formats(mixed $phone): void
    {
        $user = $this->createUser(['phone' => '0901234567']);

        $this->patchProfile($user, ['phone' => $phone])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors('phone');

        $this->assertSame('0901234567', $user->fresh()?->phone);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidPhoneProvider(): array
    {
        return [
            'too short' => ['123456'],
            'too long' => ['1234567890123456'],
            'letters' => ['+84abc123456'],
            'plus in middle' => ['849+01234567'],
            'spaces' => ['+84 901234567'],
            'non string' => [901234567],
        ];
    }

    public function test_rejects_request_without_bearer_token(): void
    {
        $this->patchJson('/api/v1/me', ['name' => 'Unauthorized'])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_rejects_invalid_bearer_token(): void
    {
        $this->patchJson('/api/v1/me', ['name' => 'Unauthorized'], [
            'Authorization' => 'Bearer invalid-token',
        ])->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_allows_unverified_user_to_update_profile(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Unverified']);

        $this->patchProfile($user, ['name' => 'Updated Unverified'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Unverified')
            ->assertJsonPath('data.emailVerifiedAt', null);

        $this->assertNull($user->fresh()?->email_verified_at);
    }

    public function test_forbidden_field_injection_does_not_change_internal_data(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 12:00:00 UTC'));
        $user = $this->createUser([
            'email' => 'protected@example.com',
            'password' => 'OriginalPassword123!',
            'avatar_path' => 'avatars/private.jpg',
            'email_verified_at' => Carbon::parse('2026-10-30 11:00:00 UTC'),
        ]);
        $originalPassword = $user->getRawOriginal('password');

        $response = $this->patchProfile($user, [
            'name' => 'Allowed Change',
            'id' => 999999,
            'email' => 'attacker@example.com',
            'password' => 'InjectedPassword123!',
            'avatar_path' => 'avatars/attacker.jpg',
            'email_verified_at' => null,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Allowed Change')
            ->assertJsonPath('data.email', 'protected@example.com')
            ->assertJsonMissingPaths([
                'data.id',
                'data.password',
                'data.password_hash',
                'data.accessToken',
                'data.token',
                'data.avatar_path',
            ]);

        $user->refresh();
        $this->assertSame('protected@example.com', $user->email);
        $this->assertSame($originalPassword, $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('OriginalPassword123!', $user->password));
        $this->assertSame('avatars/private.jpg', $user->avatar_path);
        $this->assertSame('2026-10-30 11:00:00', $user->email_verified_at?->format('Y-m-d H:i:s'));
    }

    public function test_authenticated_user_cannot_update_another_user(): void
    {
        $userA = $this->createUser([
            'name' => 'User A',
            'email' => 'update.user-a@example.com',
        ]);
        $userB = $this->createUser([
            'name' => 'User B',
            'email' => 'update.user-b@example.com',
        ]);

        $this->patchProfile($userA, [
            'name' => 'Updated User A',
            'id' => $userB->getKey(),
            'user_id' => $userB->getKey(),
        ])->assertOk()
            ->assertJsonPath('data.email', $userA->email);

        $this->assertSame('Updated User A', $userA->fresh()?->name);
        $this->assertSame('User B', $userB->fresh()?->name);
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /** @param array<string, mixed> $payload */
    private function patchProfile(User $user, array $payload): TestResponse
    {
        $accessToken = $user->createToken('update-profile-test')->plainTextToken;

        return $this->patchJson('/api/v1/me', $payload, [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
    }
}
