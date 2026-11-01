<?php

namespace App\Infrastructure\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class UserAvatarStorage
{
    private const string DIRECTORY = 'avatars';

    public function store(UploadedFile $avatar): string
    {
        // Laravel generates a random hash name and derives its extension from the detected MIME type.
        $path = Storage::disk($this->diskName())->putFile(self::DIRECTORY, $avatar);

        if ($path === false) {
            throw new RuntimeException('Unable to store avatar.');
        }

        return $path;
    }

    public function url(?string $path): ?string
    {
        if ($path === null || ! $this->isAvatarPath($path)) {
            return null;
        }

        // The configured disk creates the external URL so profile responses never expose the raw path.
        return Storage::disk($this->diskName())->url($path);
    }

    public function delete(?string $path): bool
    {
        if ($path === null || ! $this->isDeletableAvatarPath($path)) {
            return true;
        }

        return Storage::disk($this->diskName())->delete($path);
    }

    private function diskName(): string
    {
        $disk = config('filesystems.avatar_disk');

        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('Avatar storage disk is not configured.');
        }

        return $disk;
    }

    private function isAvatarPath(string $path): bool
    {
        return preg_match(
            '/\Aavatars\/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)\z/Di',
            $path,
        ) === 1;
    }

    private function isDeletableAvatarPath(string $path): bool
    {
        if (! $this->isAvatarPath($path)) {
            return false;
        }

        return preg_match('/\Aavatars\/(?:default|avatar-default)(?:[._-].*)?\z/Di', $path) !== 1;
    }
}
