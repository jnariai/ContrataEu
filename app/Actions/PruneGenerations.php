<?php

namespace App\Actions;

use App\Models\Application;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\StoredFile;

/**
 * Keeps the newest versions of an application (3 by default) and deletes older ones, rendered PDFs first (row and
 * bucket object), since the SQL cascade wouldn't fire the StoredFile hook. Never deletes the version the
 * application currently shows, or one still queued or running.
 */
class PruneGenerations
{
    public const int KEEP = 3;

    public function handle(Application $application, int $keep = self::KEEP): void
    {
        $application->refresh();

        $doomed = $application->generations()
            ->orderByDesc('version')
            ->offset($keep)
            ->get()
            ->reject(fn (Generation $generation): bool => $generation->id === $application->current_generation_id
                || ! $generation->isFinished());

        foreach ($doomed as $generation) {
            StoredFile::query()
                ->where('fileable_type', (new GeneratedDocument)->getMorphClass())
                ->whereIn('fileable_id', $generation->documents()->select('id'))
                ->each(fn (StoredFile $file) => $file->delete());

            $generation->delete();
        }
    }
}
