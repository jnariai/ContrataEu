<?php

namespace App\Actions;

use App\Casts\LookupCast;
use App\Enums\FilePurpose;
use App\Models\CvImport;
use App\Models\GeneratedDocument;
use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\Models\User;
use App\ValueObjects\StoragePath;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Writes one file to the private bucket and tracks it in `stored_files`. A model holds at most one file per purpose:
 * storing again replaces the previous file (row and object).
 */
class StoreFile
{
    public function __construct(
        private FilesystemManager $storage,
        private Config $config,
    ) {}

    public function handle(
        User $owner,
        MasterDocument|GeneratedDocument|CvImport $fileable,
        FilePurpose $purpose,
        StoragePath $path,
        UploadedFile|string $contents,
        ?string $originalName = null,
        string $mimeType = 'application/pdf',
    ): StoredFile {
        $disk = $this->config->string('filesystems.files_disk');

        if ($contents instanceof UploadedFile) {
            $mimeType = $contents->getMimeType() ?? $mimeType;
            $originalName ??= $contents->getClientOriginalName();
            $sizeBytes = (int) $contents->getSize();
            $this->storage->disk($disk)->putFileAs(dirname($path->value), $contents, basename($path->value));
        } else {
            $sizeBytes = strlen($contents);
            $this->storage->disk($disk)->put($path->value, $contents);
        }

        $previous = StoredFile::query()
            ->whereMorphedTo('fileable', $fileable)
            ->where('purpose_id', LookupCast::idOf('file_purposes', $purpose))
            ->first();

        if ($previous !== null && $previous->path === $path->value && $previous->disk === $disk) {
            $previous->refreshObject($mimeType, $sizeBytes, $originalName);

            return $previous;
        }

        try {
            $file = StoredFile::record($owner, $fileable, $purpose, $disk, $path, $mimeType, $sizeBytes, $originalName);
        } catch (Throwable $exception) {
            $this->storage->disk($disk)->delete($path->value);

            throw $exception;
        }

        $previous?->delete();

        return $file;
    }
}
