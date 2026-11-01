<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use DateInterval;
use DateTimeInterface;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_signed_link_verifies_email_without_authentication(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake([Verified::class]);

        $response = $this->getJson($this->verificationUrl($user));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Email verified successfully',
            ]);
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(
            Verified::class,
            fn (Verified $event): bool => $event->user->is($user),
        );
    }

    public function test_expired_signed_link_is_rejected_even_when_email_is_already_verified(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => '2026-10-30 08:00:00',
        ]);
        $originalVerifiedAt = $user->email_verified_at->toDateTimeString();
        Event::fake([Verified::class]);

        $response = $this->getJson(
            $this->verificationUrl($user, now()->subMinute()),
        );

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'error_code' => 'FORBIDDEN',
            ]);
        $this->assertSame(
            $originalVerifiedAt,
            $user->fresh()->email_verified_at->toDateTimeString(),
        );
        Event::assertNotDispatched(Verified::class);
    }

    public function test_tampered_url_signature_is_rejected_before_hash_validation(): void
    {
        $user = User::factory()->unverified()->create();
        $validHash = sha1($user->getEmailForVerification());
        $validUrl = $this->verificationUrl($user, hash: $validHash);
        $tamperedUrl = str_replace($validHash, str_repeat('0', 40), $validUrl);
        Event::fake([Verified::class]);

        $response = $this->getJson($tamperedUrl);

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'error_code' => 'FORBIDDEN',
            ]);
        $this->assertNull($user->fresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_validly_signed_url_with_wrong_email_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake([Verified::class]);

        $response = $this->getJson(
            $this->verificationUrl($user, hash: str_repeat('0', 40)),
        );

        $response
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Forbidden',
                'error_code' => 'FORBIDDEN',
            ]);
        $this->assertNull($user->fresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_already_verified_email_is_idempotent_and_does_not_dispatch_event(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => '2026-10-30 08:00:00',
        ]);
        $originalVerifiedAt = $user->email_verified_at->toDateTimeString();
        Event::fake([Verified::class]);

        $response = $this->getJson($this->verificationUrl($user));

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => null,
                'message' => 'Email verified successfully',
            ]);
        $this->assertSame(
            $originalVerifiedAt,
            $user->fresh()->email_verified_at->toDateTimeString(),
        );
        Event::assertNotDispatched(Verified::class);
    }

    public function test_valid_signed_link_with_missing_user_returns_not_found(): void
    {
        Event::fake([Verified::class]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'id' => 999,
                'hash' => sha1('missing@example.com'),
            ],
        );

        $response = $this->getJson($url);

        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Not found',
                'error_code' => 'NOT_FOUND',
            ]);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_failed_email_verification_persistence_returns_server_error_without_event(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake([Verified::class]);
        User::saving(
            fn (User $savingUser): bool => ! $savingUser->isDirty('email_verified_at'),
        );

        try {
            $response = $this->getJson($this->verificationUrl($user));
        } finally {
            User::flushEventListeners();
        }

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => 'INTERNAL_SERVER_ERROR',
            ]);
        $this->assertNull($user->fresh()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }

    private function verificationUrl(
        User $user,
        DateInterval|DateTimeInterface|int|null $expiration = null,
        ?string $hash = null,
    ): string {
        return URL::temporarySignedRoute(
            'verification.verify',
            $expiration ?? now()->addHour(),
            [
                'id' => $user->getKey(),
                'hash' => $hash ?? sha1($user->getEmailForVerification()),
            ],
        );
    }
}
