<?php

use App\Enums\AiProvider;
use App\Enums\DocumentType;
use App\Models\AiCredential;
use App\Models\Application;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use Inertia\Testing\AssertableInertia;

/** An application with succeeded v1 and v2 (v2 current); `$after` adds runs after them. */
function applicationWithVersions(?Closure $after = null): Application
{
    $application = Application::factory()->create();
    foreach ([1, 2] as $version) {
        $generation = Generation::factory()->for($application)->version($version)->create();
        GeneratedDocument::factory()->for($generation)->create(['type' => DocumentType::Cv, 'content' => "CV v{$version}", 'original_content' => "CV v{$version}"]);
    }
    $application->adoptGeneration($generation);

    if ($after !== null) {
        $after(Generation::factory()->for($application));
    }

    return $application->fresh();
}

it('lists only successful versions, newest first, and shows the current one', function (): void {
    $application = applicationWithVersions(fn ($factory) => $factory->failed()->create());

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applications/show')
            ->where('generation.version', 2)
            ->where('generation.documents.0.content', 'CV v2')
            ->has('versions', 2)
            ->where('versions.0.version', 2)
            ->where('versions.0.current', true)
            ->where('versions.1.version', 1)
            ->where('run', null)
            ->where('can.generate', true)
            ->where('limits.document', 50_000));
});

it('reports a failed last attempt with its message, keeping the last good version on screen', function (): void {
    $application = applicationWithVersions(fn ($factory) => $factory->failed()->create(['error' => 'Anthropic rejected the API key.']));

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lastAttemptFailed', true)
            ->where('generation.version', 2)
            ->has('failures', 1)
            ->where('failures.0.error', 'Anthropic rejected the API key.')
            ->where('failures.0.provider', 'Anthropic')
            ->has('failures.0.started_at')
            ->has('failures.0.finished_at'));
});

it('does not report an old failure once a later run succeeded', function (): void {
    $application = Application::factory()->create();
    Generation::factory()->for($application)->failed()->create();
    $success = Generation::factory()->for($application)->version(1)->create();
    $application->adoptGeneration($success);

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lastAttemptFailed', false)
            ->has('failures', 1));
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

it('shows the run in progress for its steps, keeps the current version, and blocks generating', function (): void {
    $application = applicationWithVersions(fn ($factory) => $factory->running()->create(['model' => 'claude-sonnet-5-5']));

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generation.version', 2)
            ->has('versions', 2)
            ->where('run.status.value', 'running')
            ->where('run.model', 'claude-sonnet-5-5')
            ->has('run.queued_at')
            ->has('run.started_at')
            ->where('can.generate', false));
});

it('shows no documents while the first run is queued', function (): void {
    $application = Application::factory()->create();
    Generation::factory()->for($application)->queued()->create();

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('generation', null)
            ->has('versions', 0)
            ->where('run.status.value', 'queued')
            ->where('run.started_at', null));
});

it('offers the user’s AI keys next to Generate, active first, without the key itself', function (): void {
    $application = Application::factory()->create();
    AiCredential::factory()->for($application->user)->create(['api_key' => 'sk-ant-SUPERSECRET-a1b2']);
    AiCredential::factory()->for($application->user)->asDefault()->create(['provider' => AiProvider::OpenRouter, 'name' => 'Free tier', 'model' => 'openrouter/free']);
    AiCredential::factory()->create();

    $response = $this->actingAs($application->user)->get(route('applications.show', $application));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('aiKeys', 2)
        ->where('aiKeys.0.name', 'Free tier')
        ->where('aiKeys.0.provider', 'OpenRouter')
        ->where('aiKeys.0.model', 'openrouter/free')
        ->where('aiKeys.0.active', true)
        ->where('aiKeys.1.name', 'Anthropic')
        ->where('aiKeys.1.active', false)
        ->missing('aiKeys.0.api_key'));
    expect($response->getContent())->not->toContain('SUPERSECRET');
});
