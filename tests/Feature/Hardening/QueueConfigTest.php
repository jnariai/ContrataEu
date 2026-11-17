<?php

use App\Jobs\RunGenerationJob;

it('hands a running generation to only one worker: retry_after outlasts the job timeout', function (): void {
    $job = new ReflectionClass(RunGenerationJob::class);
    $timeout = $job->getProperty('timeout')->getDefaultValue();

    // Production runs the database queue (tests use sync, which has no retry_after).
    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($timeout);
});
