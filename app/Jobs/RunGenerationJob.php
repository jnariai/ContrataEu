<?php

namespace App\Jobs;

use App\Actions\RunGeneration;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Runs one generation in the queue. One try only: the AI call costs the user money, so a failure is shown to them
 * (with "Generate" again) instead of being retried silently. Messages are written in the user's UI language.
 */
class RunGenerationJob implements ShouldQueue
{
    use Localizable;
    use Queueable;

    public int $tries = 1;

    /** Above the AI client's own 300 s timeout, below the queue's retry_after. */
    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public function __construct(public Generation $generation) {}

    public function handle(RunGeneration $runGeneration): void
    {
        $this->generation->loadMissing('application.user');

        $this->withLocale(
            $this->generation->application->user->preferredLocale(),
            fn () => $runGeneration->handle($this->generation),
        );
    }

    /** The worker died or timed out mid-run: never leave the generation spinning forever. */
    public function failed(?Throwable $exception): void
    {
        $generation = $this->generation->fresh(['application.user']);

        if ($generation === null || $generation->isFinished()) {
            return;
        }

        $this->withLocale(
            $generation->application->user->preferredLocale(),
            fn () => $generation->fail(RunGeneration::unexpectedFailureMessage(), now()->toImmutable()),
        );
    }
}
