<?php

namespace Tests\Feature\Auth;

use App\Constants\ErrorCode;
use App\Infrastructure\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class UploadAvatarTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('supportedImageExtensionProvider')]
    public function test_uploads_supported_image_and_returns_200(string $extension): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'name' => 'Avatar User',
            'email' => 'avatar.'.$extension.'@example.com',
            'avatar_path' => null,
        ]);
        $file = UploadedFile::fake()->image('client-name.'.$extension);

        $response = $this->uploadAvatar($user, $file);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Avatar User')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPaths([
                'data.id',
                'data.avatar_path',
                'data.password',
                'data.accessToken',
                'data.token',
            ]);

        $avatarPath = $user->fresh()?->avatar_path;
        $this->assertIsString($avatarPath);
        $this->assertMatchesRegularExpression(
            '/\Aavatars\/[A-Za-z0-9]{40}\.(?:jpg|jpeg|png|webp)\z/',
            $avatarPath,
        );
        $this->assertNotSame('avatars/client-name.'.$extension, $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);
        $response->assertJsonPath('data.avatarUrl', Storage::disk('public')->url($avatarPath));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedImageExtensionProvider(): array
    {
        return [
            'JPEG' => ['jpg'],
            'PNG' => ['png'],
            'WebP' => ['webp'],
        ];
    }

    public function test_returns_422_when_avatar_is_missing(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->postAvatarRequest($this->issueToken($user));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonPath('errors.avatar.0', 'The avatar field is required.');
        Storage::disk('public')->assertEmpty();
        $this->assertNull($user->fresh()?->avatar_path);
    }

    #[DataProvider('unsupportedFileProvider')]
    public function test_returns_422_for_unsupported_or_non_image_file(string $kind): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->uploadAvatar($user, $this->unsupportedFile($kind));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors('avatar');
        Storage::disk('public')->assertEmpty();
        $this->assertNull($user->fresh()?->avatar_path);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedFileProvider(): array
    {
        return [
            'GIF image' => ['gif'],
            'SVG image' => ['svg'],
            'text disguised as JPEG' => ['fake-jpeg'],
        ];
    }

    public function test_returns_422_when_avatar_exceeds_two_megabytes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('large.jpg')->size(2049);

        $response = $this->uploadAvatar($user, $file);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error_code', ErrorCode::VALIDATION_ERROR)
            ->assertJsonValidationErrors('avatar');
        Storage::disk('public')->assertEmpty();
        $this->assertNull($user->fresh()?->avatar_path);
    }

    public function test_returns_401_without_bearer_token(): void
    {
        Storage::fake('public');

        $this->postAvatarRequest(null, UploadedFile::fake()->image('avatar.jpg'))
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
        Storage::disk('public')->assertEmpty();
    }

    public function test_returns_401_with_invalid_bearer_token(): void
    {
        Storage::fake('public');

        $this->postAvatarRequest('invalid-token', UploadedFile::fake()->image('avatar.jpg'))
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Unauthenticated',
                'error_code' => ErrorCode::UNAUTHORIZED,
            ]);
        Storage::disk('public')->assertEmpty();
    }

    public function test_unverified_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->unverified()->create();

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('avatar.jpg'));

        $response
            ->assertOk()
            ->assertJsonPath('data.emailVerifiedAt', null);
        $this->assertNotNull($user->fresh()?->avatar_path);
    }

    public function test_second_upload_replaces_path_and_deletes_previous_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => null]);

        $this->uploadAvatar($user, UploadedFile::fake()->image('first.jpg'))
            ->assertOk();
        $firstAvatarPath = $user->fresh()?->avatar_path;
        $this->assertIsString($firstAvatarPath);
        Storage::disk('public')->assertExists($firstAvatarPath);

        $this->uploadAvatar($user, UploadedFile::fake()->image('second.png'))
            ->assertOk();

        $newAvatarPath = $user->fresh()?->avatar_path;
        $this->assertIsString($newAvatarPath);
        $this->assertNotSame($firstAvatarPath, $newAvatarPath);
        Storage::disk('public')->assertExists($newAvatarPath);
        Storage::disk('public')->assertMissing($firstAvatarPath);
    }

    public function test_storage_write_returning_false_does_not_update_database(): void
    {
        config(['filesystems.avatar_disk' => 'failing-avatar-disk']);
        $user = User::factory()->create(['avatar_path' => 'avatars/existing.jpg']);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn(false);
        Storage::shouldReceive('disk')
            ->once()
            ->with('failing-avatar-disk')
            ->andReturn($disk);

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('avatar.jpg'));

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => ErrorCode::INTERNAL_SERVER_ERROR,
            ]);
        $this->assertSame('avatars/existing.jpg', $user->fresh()?->avatar_path);
    }

    public function test_storage_write_exception_is_reported_as_server_error_without_database_update(): void
    {
        config(['filesystems.avatar_disk' => 'throwing-avatar-disk']);
        Exceptions::fake(RuntimeException::class);
        $storageException = new RuntimeException('Simulated storage write failure.');
        $user = User::factory()->create(['avatar_path' => 'avatars/existing.jpg']);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andThrow($storageException);
        Storage::shouldReceive('disk')
            ->once()
            ->with('throwing-avatar-disk')
            ->andReturn($disk);

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('avatar.jpg'));

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => ErrorCode::INTERNAL_SERVER_ERROR,
            ]);
        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => $exception === $storageException,
        );
        $this->assertSame('avatars/existing.jpg', $user->fresh()?->avatar_path);
    }

    public function test_database_failure_removes_new_file_and_preserves_database_path(): void
    {
        Storage::fake('public');
        $previousAvatarPath = 'avatars/previous.jpg';
        Storage::disk('public')->put($previousAvatarPath, 'previous image');
        $user = User::factory()->create(['avatar_path' => $previousAvatarPath]);
        User::updated(static function (): never {
            throw new RuntimeException('Simulated failure before transaction commit.');
        });

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('avatar.jpg'));

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'message' => 'Internal server error',
                'error_code' => ErrorCode::INTERNAL_SERVER_ERROR,
            ]);
        Storage::disk('public')->assertExists($previousAvatarPath);
        Storage::disk('public')->assertCount('avatars', 1);
        $this->assertSame($previousAvatarPath, $user->fresh()?->avatar_path);
    }

    public function test_cleanup_exception_does_not_replace_original_database_exception(): void
    {
        config(['filesystems.avatar_disk' => 'cleanup-throwing-disk']);
        Exceptions::fake(RuntimeException::class);
        Log::spy();
        $databaseException = new RuntimeException('Simulated database failure.');
        $cleanupException = new RuntimeException('Simulated cleanup failure.');
        $user = User::factory()->create(['avatar_path' => 'avatars/existing.jpg']);
        $repository = $this->mock(UserRepository::class);
        $repository->shouldReceive('replaceAvatarPath')
            ->once()
            ->andThrow($databaseException);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn('avatars/new-avatar.jpg');
        $disk->shouldReceive('delete')
            ->once()
            ->with('avatars/new-avatar.jpg')
            ->andThrow($cleanupException);
        Storage::shouldReceive('disk')
            ->with('cleanup-throwing-disk')
            ->andReturn($disk);

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('avatar.jpg'));

        $response->assertInternalServerError();
        Exceptions::assertReported(
            fn (RuntimeException $exception): bool => $exception === $databaseException,
        );
        Log::shouldHaveReceived('warning')->once()->with(
            'Failed to clean up a new avatar after a database error.',
            [
                'user_id' => $user->getKey(),
                'exception_class' => RuntimeException::class,
            ],
        );
        $this->assertSame('avatars/existing.jpg', $user->fresh()?->avatar_path);
    }

    public function test_old_avatar_cleanup_exception_keeps_committed_new_avatar_and_returns_200(): void
    {
        config(['filesystems.avatar_disk' => 'old-cleanup-throwing-disk']);
        Log::spy();
        $user = User::factory()->create(['avatar_path' => 'avatars/previous.jpg']);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn('avatars/new-avatar.jpg');
        $disk->shouldReceive('delete')
            ->once()
            ->with('avatars/previous.jpg')
            ->andThrow(new RuntimeException('Simulated old avatar cleanup failure.'));
        $disk->shouldReceive('url')
            ->once()
            ->with('avatars/new-avatar.jpg')
            ->andReturn('https://cdn.example.test/avatars/new-avatar.jpg');
        Storage::shouldReceive('disk')
            ->with('old-cleanup-throwing-disk')
            ->andReturn($disk);

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('avatar.jpg'));

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.avatarUrl',
                'https://cdn.example.test/avatars/new-avatar.jpg',
            );
        $this->assertSame('avatars/new-avatar.jpg', $user->fresh()?->avatar_path);
        Log::shouldHaveReceived('warning')->once()->with(
            'Failed to delete a previous user avatar.',
            [
                'user_id' => $user->getKey(),
                'exception_class' => RuntimeException::class,
            ],
        );
    }

    public function test_configured_disk_is_used_for_upload_delete_and_url(): void
    {
        config(['filesystems.avatar_disk' => 'avatar-cloud']);
        Storage::fake('public');
        Storage::fake('avatar-cloud', ['url' => 'https://cdn.example.test']);
        $previousAvatarPath = 'avatars/previous.jpg';
        Storage::disk('avatar-cloud')->put($previousAvatarPath, 'previous image');
        $user = User::factory()->create(['avatar_path' => $previousAvatarPath]);

        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('avatar.webp'));

        $newAvatarPath = $user->fresh()?->avatar_path;
        $this->assertIsString($newAvatarPath);
        $response
            ->assertOk()
            ->assertJsonPath(
                'data.avatarUrl',
                'https://cdn.example.test/'.$newAvatarPath,
            );
        Storage::disk('avatar-cloud')->assertExists($newAvatarPath);
        Storage::disk('avatar-cloud')->assertMissing($previousAvatarPath);
        Storage::disk('public')->assertEmpty();
    }

    public function test_get_profile_returns_new_avatar_url_after_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => null]);
        $uploadResponse = $this->uploadAvatar(
            $user,
            UploadedFile::fake()->image('avatar.jpg'),
        );
        $avatarUrl = $uploadResponse->json('data.avatarUrl');

        $response = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer '.$this->issueToken($user),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', $avatarUrl)
            ->assertJsonMissingPath('data.avatar_path');
    }

    public function test_user_cannot_change_another_users_avatar(): void
    {
        Storage::fake('public');
        $userA = User::factory()->create([
            'email' => 'avatar.user-a@example.com',
            'avatar_path' => null,
        ]);
        $userBAvatarPath = 'avatars/user-b.jpg';
        Storage::disk('public')->put($userBAvatarPath, 'user B image');
        $userB = User::factory()->create([
            'email' => 'avatar.user-b@example.com',
            'avatar_path' => $userBAvatarPath,
        ]);

        $response = $this->postAvatarRequest(
            $this->issueToken($userA),
            UploadedFile::fake()->image('user-a.jpg'),
            ['id' => $userB->getKey(), 'user_id' => $userB->getKey()],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.email', $userA->email);
        $this->assertNotNull($userA->fresh()?->avatar_path);
        $this->assertSame($userBAvatarPath, $userB->fresh()?->avatar_path);
        Storage::disk('public')->assertExists($userBAvatarPath);
    }

    public function test_default_and_outside_files_are_not_deleted_when_avatar_changes(): void
    {
        Storage::fake('public');
        $defaultAvatarPath = 'avatars/default.png';
        $outsidePath = 'shared/not-an-avatar.jpg';
        Storage::disk('public')->put($defaultAvatarPath, 'default image');
        Storage::disk('public')->put($outsidePath, 'shared image');
        $defaultUser = User::factory()->create(['avatar_path' => $defaultAvatarPath]);
        $outsideUser = User::factory()->create(['avatar_path' => $outsidePath]);

        $this->uploadAvatar($defaultUser, UploadedFile::fake()->image('first.jpg'))->assertOk();
        $this->uploadAvatar($outsideUser, UploadedFile::fake()->image('second.jpg'))->assertOk();

        Storage::disk('public')->assertExists($defaultAvatarPath);
        Storage::disk('public')->assertExists($outsidePath);
    }

    private function unsupportedFile(string $kind): UploadedFile
    {
        return match ($kind) {
            'gif' => UploadedFile::fake()->image('avatar.gif'),
            'svg' => UploadedFile::fake()->createWithContent(
                'avatar.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
            ),
            'fake-jpeg' => UploadedFile::fake()->createWithContent(
                'avatar.jpg',
                'This is not an image.',
            )->mimeType('text/plain'),
        };
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('avatar-upload-test')->plainTextToken;
    }

    private function uploadAvatar(User $user, UploadedFile $avatar): TestResponse
    {
        return $this->postAvatarRequest($this->issueToken($user), $avatar);
    }

    /**
     * @param  array<string, mixed>  $extraFields
     */
    private function postAvatarRequest(
        ?string $accessToken,
        ?UploadedFile $avatar = null,
        array $extraFields = [],
    ): TestResponse {
        $headers = ['Accept' => 'application/json'];

        if ($accessToken !== null) {
            $headers['Authorization'] = 'Bearer '.$accessToken;
        }

        return $this->post(
            '/api/v1/me/avatar',
            array_filter([
                ...$extraFields,
                'avatar' => $avatar,
            ], static fn (mixed $value): bool => $value !== null),
            $headers,
        );
    }
}
