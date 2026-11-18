<?php

use App\Actions\StartGeneration;
use App\Enums\AiProvider;
use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Exceptions\GenerationCannotStart;
use App\Jobs\RunGenerationJob;
use App\Models\AiCredential;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Generation;
use App\Models\MasterDocument;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/** A user who finished setup, with one application. */
function readyApplication(array $attributes = []): Application
{
    $user = User::factory()->create();
    CandidateProfile::factory()->for($user)->create();
    MasterDocument::factory()->for($user)->create();
    AiCredential::factory()->for($user)->asDefault()->create(['provider' => AiProvider::Groq, 'model' => 'llama-3.3-70b-versatile']);

    return Application::factory()->for($user)->create($attributes);
}

beforeEach(fn () => Queue::fake());

it('queues an unnumbered run with a snapshot of the key provider, model and language', function (): void {
    $application = readyApplication(['language' => Language::PtBr]);
    Generation::factory()->for($application)->version(4)->create();

    $this->actingAs($application->user)
        ->post(route('generations.store', $application))
        ->assertRedirect(route('applications.show', $application))
        ->assertInertiaFlash('toast.type', 'success');

    $generation = $application->generations()->latest('id')->first();
    expect($generation->version)->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->provider)->toBe(AiProvider::Groq)
        ->and($generation->model)->toBe('llama-3.3-70b-versatile')
        ->and($generation->language)->toBe(Language::PtBr);
    Queue::assertPushed(RunGenerationJob::class, fn (RunGenerationJob $job): bool => $job->generation->is($generation));
});

it('does not start without the setup it needs', function (Closure $breakSetup, string $code): void {
    $application = readyApplication();
    $breakSetup($application->user);

    expect(fn () => app(StartGeneration::class)->handle($application))
        ->toThrow(fn (GenerationCannotStart $e) => expect($e->errorCode)->toBe($code));

    expect(Generation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no candidate profile' => [fn (User $user) => $user->candidateProfile()->delete(), 'generation.missing_profile'],
    'no master CV' => [fn (User $user) => $user->masterDocuments()->delete(), 'generation.missing_master_cv'],
    'no AI key' => [fn (User $user) => $user->aiCredentials()->delete(), 'generation.missing_ai_key'],
]);

it('does not start while another generation is queued or running', function (Closure $state): void {
    $application = readyApplication();
    $state(Generation::factory()->for($application)->version(1))->create();

    $this->actingAs($application->user)
        ->from(route('applications.show', $application))
        ->post(route('generations.store', $application))
        ->assertRedirect(route('applications.show', $application))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Generation::query()->count())->toBe(1);
    Queue::assertNothingPushed();
})->with([
    'queued' => [fn ($factory) => $factory->queued()],
    'running' => [fn ($factory) => $factory->running()],
]);

it('allows at most 10 generations per hour per user', function (): void {
    $application = readyApplication();
    $start = app(StartGeneration::class);

    foreach (range(1, StartGeneration::MAX_PER_HOUR) as $attempt) {
        $start->handle($application)->fail('done', now()->toImmutable());
    }

    expect(fn () => $start->handle($application))
        ->toThrow(fn (GenerationCannotStart $e) => expect($e->errorCode)->toBe('generation.rate_limited'));

    $this->travel(61)->minutes();
    expect($start->handle($application)->status)->toBe(GenerationStatus::Queued);
});

it('requires a verified email', function (): void {
    $application = readyApplication();
    $application->user->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($application->user)
        ->post(route('generations.store', $application))
        ->assertRedirect(route('verification.notice'));
});

it("answers 404 for another user's application", function (): void {
    $application = readyApplication();

    $this->actingAs(User::factory()->create())
        ->post(route('generations.store', $application))
        ->assertNotFound();

    Queue::assertNothingPushed();
});
