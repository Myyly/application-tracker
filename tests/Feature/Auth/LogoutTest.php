<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PASSWORD = 'LogoutPassword123!';

    public function test_logs_out_with_valid_bearer_token(): void
    {
        $user = $this->createUser('logout.success@example.com');
        $accessToken = $this->login($user);

        $this->logout($accessToken)
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Logged out successfully',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_revoked_token_cannot_access_profile(): void
    {
        $user = $this->createUser('logout.revoked@example.com');
        $accessToken = $this->login($user);

        $this->logout($accessToken)->assertOk();
        Auth::forgetGuards();

        $this->getProfile($accessToken)
            ->assertUnauthorized()
            ->assertExactJson($this->unauthenticatedResponse());
    }

    public function test_rejects_logout_without_bearer_token(): void
    {
        $this->postJson('/api/v1/logout')
            ->assertUnauthorized()
            ->assertExactJson($this->unauthenticatedResponse());
    }

    public function test_rejects_logout_with_invalid_bearer_token(): void
    {
        $this->logout('invalid-token')
            ->assertUnauthorized()
            ->assertExactJson($this->unauthenticatedResponse());
    }

    public function test_logout_revokes_only_current_token_and_keeps_other_login_valid(): void
    {
        $user = $this->createUser('logout.multi-device@example.com');
        $tokenA = $this->login($user);
        $tokenB = $this->login($user);

        $this->logout($tokenA)->assertOk();
        Auth::forgetGuards();

        $this->getProfile($tokenA)->assertUnauthorized();
        $this->getProfile($tokenB)
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $this->tokenId($tokenB),
            'tokenable_id' => $user->getKey(),
        ]);
    }

    public function test_unverified_user_can_logout(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'logout.unverified@example.com',
            'password' => self::PASSWORD,
        ]);
        $accessToken = $this->login($user);

        $this->logout($accessToken)->assertOk();

        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_repeated_logout_with_revoked_token_is_unauthorized(): void
    {
        $user = $this->createUser('logout.repeated@example.com');
        $accessToken = $this->login($user);

        $this->logout($accessToken)->assertOk();
        Auth::forgetGuards();

        // The first call has the idempotent revoke effect; middleware rejects reuse of the deleted token.
        $this->logout($accessToken)
            ->assertUnauthorized()
            ->assertExactJson($this->unauthenticatedResponse());
    }

    public function test_logout_preserves_user_and_non_current_token_data(): void
    {
        $user = $this->createUser('logout.database@example.com');
        $originalUser = $user->fresh()->getRawOriginal();
        $currentToken = $this->login($user);
        $otherToken = $this->login($user);

        $this->logout($currentToken)->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $this->tokenId($currentToken),
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $this->tokenId($otherToken),
            'tokenable_id' => $user->getKey(),
        ]);
        $this->assertSame($originalUser, $user->fresh()->getRawOriginal());
    }

    public function test_logout_response_does_not_expose_sensitive_data(): void
    {
        $user = $this->createUser('logout.security@example.com');
        $accessToken = $this->login($user);

        $this->logout($accessToken)
            ->assertOk()
            ->assertJsonMissingPaths([
                'id',
                'data.id',
                'data.user',
                'accessToken',
                'data.accessToken',
                'password',
                'data.password',
            ])
            ->assertDontSee($accessToken);
    }

    private function createUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk()->json('data.accessToken');
    }

    private function logout(string $accessToken): TestResponse
    {
        return $this->postJson('/api/v1/logout', headers: [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
    }

    private function getProfile(string $accessToken): TestResponse
    {
        return $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
    }

    private function tokenId(string $accessToken): int
    {
        return (int) explode('|', $accessToken, 2)[0];
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
