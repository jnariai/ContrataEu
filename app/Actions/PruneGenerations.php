<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Models\Application;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\StoredFile;
use Illuminate\Support\Collection;

/**
 * Keeps the newest successful versions of an application (3) and, separately, its newest failed runs (3, the
 * failure log), so a failure never pushes out a good version. Rendered PDFs are deleted first (row and bucket
 * object), since the SQL cascade wouldn't fire the StoredFile hook. Never deletes the version the application
 * shows, or a run still queued or running.
 */
class PruneGenerations
{
    public const int KEEP = 3;

    public const int KEEP_FAILURES = 3;

    public function handle(Application $application, int $keep = self::KEEP, int $keepFailures = self::KEEP_FAILURES): void
    {
        $application->refresh();

        $oldVersions = $application->generations()
            ->inStatus(GenerationStatus::Succeeded)
            ->orderByDesc('version')
            ->offset($keep)
            ->get();

        $oldFailures = $application->generations()
            ->inStatus(GenerationStatus::Failed)
            ->orderByDesc('id')
            ->offset($keepFailures)
            ->get();

        $this->delete($oldVersions->merge($oldFailures)->reject(
            fn (Generation $generation): bool => $generation->id === $application->current_generation_id,
        ));
    }

    /** @param Collection<int, Generation> $generations */
    private function delete(Collection $generations): void
    {
        foreach ($generations as $generation) {
            StoredFile::query()
                ->where('fileable_type', (new GeneratedDocument)->getMorphClass())
                ->whereIn('fileable_id', $generation->documents()->select('id'))
                ->each(fn (StoredFile $file) => $file->delete());

            $generation->delete();
        }
    }
}
