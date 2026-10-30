<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GetProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_authenticated_users_profile_with_exact_secure_fields(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00 UTC'));
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'profile@example.com',
            'date_of_birth' => '2000-05-20',
            'gender' => USER_GENDER_FEMALE,
            'phone' => '0901234567',
            'avatar_path' => 'avatars/internal.jpg',
            'email_verified_at' => Carbon::parse('2026-10-30 11:00:00 UTC'),
        ]);
        $accessToken = $this->issueToken($user);

        $response = $this->getProfile($accessToken);

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'name' => 'Test User',
                    'email' => 'profile@example.com',
                    'dateOfBirth' => '2000-05-20',
                    'gender' => USER_GENDER_FEMALE,
                    'phone' => '0901234567',
                    'avatarUrl' => null,
                    'emailVerifiedAt' => '2026-10-30T11:00:00+00:00',
                ],
            ]);
    }

    public function test_rejects_request_without_bearer_token(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_rejects_invalid_bearer_token(): void
    {
        $this->getProfile('invalid-token')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_rejects_expired_token_through_sanctum_guard(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 12:00:00 UTC'));
        $user = User::factory()->create();
        $accessToken = $this->issueToken($user, now()->addMinute());

        $this->travel(61)->seconds();

        $this->getProfile($accessToken)
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_rejects_revoked_token_through_sanctum_guard(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('profile-test');
        $accessToken = $token->plainTextToken;
        $token->accessToken->delete();

        $this->getProfile($accessToken)
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
    }

    public function test_allows_unverified_user_and_returns_null_verification_time(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'Unverified User',
            'email' => 'profile.unverified@example.com',
        ]);
        $accessToken = $this->issueToken($user);

        $response = $this->getProfile($accessToken);

        $response
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.id')
            ->assertJsonPath('data.emailVerifiedAt', null);
        $this->assertNull($user->email_verified_at);
    }

    public function test_preserves_nullable_profile_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Nullable Profile',
            'email' => 'profile.nullable@example.com',
            'date_of_birth' => null,
            'gender' => null,
            'phone' => null,
            'avatar_path' => null,
        ]);
        $accessToken = $this->issueToken($user);

        $response = $this->getProfile($accessToken);

        $response
            ->assertOk()
            ->assertJsonPath('data.dateOfBirth', null)
            ->assertJsonPath('data.gender', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.avatarUrl', null);
    }

    public function test_authenticated_user_cannot_receive_another_users_profile(): void
    {
        $userA = User::factory()->create([
            'name' => 'User A',
            'email' => 'profile.user-a@example.com',
        ]);
        $userB = User::factory()->create([
            'name' => 'User B',
            'email' => 'profile.user-b@example.com',
        ]);
        $accessToken = $this->issueToken($userA);

        $response = $this->getJson('/api/v1/me?user_id='.$userB->getKey(), [
            'Authorization' => 'Bearer '.$accessToken,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.email', $userA->email)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissing([
                'name' => $userB->name,
                'email' => $userB->email,
            ]);
    }

    public function test_default_api_user_route_is_not_available(): void
    {
        $this->getJson('/api/user')->assertNotFound();
    }

    private function issueToken(User $user, ?DateTimeInterface $expiresAt = null): string
    {
        return $user->createToken('profile-test', ['*'], $expiresAt)->plainTextToken;
    }

    private function getProfile(string $accessToken): TestResponse
    {
        return $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
    }
}
