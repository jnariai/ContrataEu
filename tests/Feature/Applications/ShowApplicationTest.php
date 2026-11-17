<?php

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use Inertia\Testing\AssertableInertia;

/** An application with succeeded v1 and v2 (v2 current) and the given state for v3. */
function applicationWithVersions(?Closure $third = null): Application
{
    $application = Application::factory()->create();
    foreach ([1, 2] as $version) {
        $generation = Generation::factory()->for($application)->version($version)->create();
        GeneratedDocument::factory()->for($generation)->create(['type' => DocumentType::Cv, 'content' => "CV v{$version}", 'original_content' => "CV v{$version}"]);
    }
    $application->adoptGeneration($generation);

    if ($third !== null) {
        $third(Generation::factory()->for($application)->version(3));
    }

    return $application->fresh();
}

it('shows the current version and lists every kept version newest first', function (): void {
    $application = applicationWithVersions(fn ($factory) => $factory->failed()->create());

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applications/show')
            ->where('generation.version', 2)
            ->where('generation.documents.0.content', 'CV v2')
            ->where('versions.0.version', 3)
            ->where('versions.0.status.value', 'failed')
            ->where('versions.0.error', 'The AI provider rejected the request.')
            ->where('versions.1.current', true)
            ->where('versions.2.current', false)
            ->where('can.generate', true)
            ->where('limits.document', 50_000));
});

it('shows the version picked in the query string', function (): void {
    $application = applicationWithVersions();

    $this->actingAs($application->user)->get(route('applications.show', [$application, 'version' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generation.version', 1)
            ->where('generation.documents.0.content', 'CV v1'));
});

it('falls back to the current version for a version that does not exist', function (): void {
    $application = applicationWithVersions();

    $this->actingAs($application->user)->get(route('applications.show', [$application, 'version' => 9]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('generation.version', 2));
});

it('keeps showing the current version while a new one runs, and blocks generating', function (): void {
    $application = applicationWithVersions(fn ($factory) => $factory->running()->create());

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generation.version', 2)
            ->where('versions.0.status.value', 'running')
            ->where('can.generate', false));
});

it('shows the latest run when nothing has succeeded yet', function (): void {
    $application = Application::factory()->create();
    Generation::factory()->for($application)->queued()->create();

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generation.status.value', 'queued')
            ->has('versions', 1));
});
