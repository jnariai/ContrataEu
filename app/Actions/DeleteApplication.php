<?php

namespace App\Actions;

use App\Models\Application;
use App\Models\GeneratedDocument;
use App\Models\StoredFile;
use App\ValueObjects\StoragePath;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\FilesystemManager;

/**
 * Deletes an application with its generations. Rendered PDFs are deleted first (row and object), because the SQL
 * cascade that removes the generations wouldn't fire the StoredFile hook; the application's folder in the bucket is
 * then wiped as a safety net.
 */
class DeleteApplication
{
    public function __construct(
        private FilesystemManager $storage,
        private Config $config,
    ) {}

    public function handle(Application $application): void
    {
        StoredFile::query()
            ->where('fileable_type', (new GeneratedDocument)->getMorphClass())
            ->whereIn('fileable_id', GeneratedDocument::query()
                ->select('generated_documents.id')
                ->join('generations', 'generations.id', '=', 'generated_documents.generation_id')
                ->where('generations.application_id', $application->id))
            ->each(fn (StoredFile $file) => $file->delete());

        $directory = StoragePath::applicationDirectory($application->user_id, $application->id);

        $application->delete();

        $this->storage->disk($this->config->string('filesystems.files_disk'))->deleteDirectory($directory);
    }
}
