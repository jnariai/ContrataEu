<?php

namespace App\Actions;

use App\Models\StoredFile;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * A short-lived signed download URL. The bucket is private, so this (after a policy check) is the only way in.
 */
class GetFileUrl
{
    public const int TTL_MINUTES = 5;

    public function __construct(private FilesystemManager $storage) {}

    public function handle(StoredFile $file, ?string $downloadName = null): string
    {
        $name = $downloadName ?? $file->original_name ?? basename($file->path);

        return $this->storage->disk($file->disk)->temporaryUrl(
            $file->path,
            now()->addMinutes(self::TTL_MINUTES),
            ['ResponseContentDisposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $name,
                Str::ascii($name) ?: 'download',
            )],
        );
    }
}
