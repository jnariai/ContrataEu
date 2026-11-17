<?php

namespace App\Actions;

use App\Models\User;
use App\ValueObjects\StoragePath;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

/**
 * Deletes a user with everything they own. Rows go through the database cascades; files go by wiping the
 * user's prefix (`users/{id}/`) on every disk they used, since cascades don't fire the StoredFile hook.
 */
class DeleteAccount
{
    public function __construct(
        private FilesystemManager $storage,
        private Config $config,
    ) {}

    public function handle(User $user): void
    {
        $directory = StoragePath::userDirectory($user->id);
        $disks = $user->storedFiles()->distinct()->pluck('disk')
            ->push($this->config->string('filesystems.files_disk'))
            ->unique();

        $user->delete();

        foreach ($disks as $disk) {
            try {
                $this->storage->disk($disk)->deleteDirectory($directory);
            } catch (Throwable $exception) {
                // The account is already gone; leftover objects are logged for cleanup instead of failing the request.
                report($exception);
            }
        }
    }
}
