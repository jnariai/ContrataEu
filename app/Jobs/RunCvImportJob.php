<?php

namespace App\Jobs;

use App\Actions\RunCvImport;
use App\Models\CvImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Reads one CV PDF in the queue. One try only, like generations: the AI call costs the user money. Messages are
 * written in the user's UI language. A discarded import (model gone) simply drops the job.
 */
#[DeleteWhenMissingModels]
class RunCvImportJob implements ShouldQueue
{
    use Localizable;
    use Queueable;

    public int $tries = 1;

    /** Above the AI client's own 300 s timeout, below the queue's retry_after. */
    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public function __construct(public CvImport $import) {}

    public function handle(RunCvImport $runCvImport): void
    {
        $this->import->loadMissing('user');

        $this->withLocale(
            $this->import->user->preferredLocale(),
            fn () => $runCvImport->handle($this->import),
        );
    }

    /** The worker died or timed out mid-run: never leave the import spinning forever. */
    public function failed(?Throwable $exception): void
    {
        $import = $this->import->fresh(['user']);

        if ($import === null || $import->isFinished()) {
            return;
        }

        $this->withLocale(
            $import->user->preferredLocale(),
            fn () => $import->fail(RunCvImport::unexpectedFailureMessage(), now()->toImmutable()),
        );
    }
}
