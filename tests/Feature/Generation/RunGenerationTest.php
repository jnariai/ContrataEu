<?php

use App\Actions\PruneGenerations;
use App\Actions\RunGeneration;
use App\Enums\AiProvider;
use App\Enums\ApplicationStatus;
use App\Enums\CvConvention;
use App\Enums\DocumentType;
use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Enums\PromptKey;
use App\Jobs\RunGenerationJob;
use App\Models\AiCredential;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\GeneratedAnswer;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\MasterDocument;
use App\Models\PromptOverride;
use App\Models\ScreeningQuestion;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Request as StructuredRequest;

/** A queued generation for a user who finished setup. */
function queuedGeneration(array $applicationAttributes = [], ?User $user = null): Generation
{
    $user ??= User::factory()->create();
    CandidateProfile::factory()->for($user)->create(['full_name' => 'Jane Doe']);
    MasterDocument::factory()->for($user)->create(['content' => '# Jane Doe MASTER']);
    AiCredential::factory()->for($user)->asDefault()->create(['provider' => AiProvider::Anthropic, 'api_key' => 'sk-ant-user-key-1234']);
    $application = Application::factory()->for($user)->create($applicationAttributes);

    return Generation::factory()->for($application)->queued()->create([
        'provider' => AiProvider::Anthropic,
        'model' => 'claude-sonnet-5-5',
        'language' => $application->language,
    ]);
}

it('saves the report, documents and answers, and shows this version on the application', function (): void {
    $generation = queuedGeneration(['wants_cover_letter' => true]);
    $application = $generation->application;
    $first = ScreeningQuestion::factory()->for($application)->create(['question' => 'Why us?', 'position' => 1]);
    $second = ScreeningQuestion::factory()->for($application)->create(['question' => 'Notice?', 'position' => 2]);
    Prism::fake([fakeGenerationResponse(generationAnswer([
        'cover_letter' => 'Dear Acme team, …',
        'screening_answers' => [
            ['question_number' => 2, 'answer' => 'One month.'],
            ['question_number' => 1, 'answer' => 'Because payments.'],
            ['question_number' => 9, 'answer' => 'Not asked.'],
        ],
    ]), 1500, 2500)]);

    app(RunGeneration::class)->handle($generation);

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->report?->keywordCoverage->percentage())->toBe(75)
        ->and($generation->input_tokens)->toBe(1500)
        ->and($generation->output_tokens)->toBe(2500)
        ->and($generation->started_at)->not->toBeNull()
        ->and($generation->finished_at)->not->toBeNull()
        ->and($generation->documents()->get()->mapWithKeys(fn (GeneratedDocument $d) => [$d->type->value => $d->content])->all())
        ->toEqual(['cv' => trim(generationAnswer()['cv_markdown']), 'cover_letter' => 'Dear Acme team, …'])
        ->and(GeneratedAnswer::query()->where('screening_question_id', $first->id)->value('content'))->toBe('Because payments.')
        ->and(GeneratedAnswer::query()->where('screening_question_id', $second->id)->value('content'))->toBe('One month.')
        ->and(GeneratedAnswer::query()->count())->toBe(2)
        ->and($application->fresh()->current_generation_id)->toBe($generation->id)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Ready);
});

it("sends the user's key, overrides and job to the model", function (): void {
    $generation = queuedGeneration(['language' => Language::PtBr, 'job_description' => 'JD-TEXT']);
    PromptOverride::factory()->for($generation->application->user)->create(['key' => PromptKey::Playbook, 'content' => 'MY-PLAYBOOK']);
    $fake = Prism::fake([fakeGenerationResponse(generationAnswer())]);

    app(RunGeneration::class)->handle($generation);

    $fake->assertProviderConfig(['api_key' => 'sk-ant-user-key-1234']);
    $fake->assertRequest(function (array $requests): void {
        /** @var StructuredRequest $request */
        $request = $requests[0];
        $system = collect($request->systemPrompts())->map(fn ($message) => $message->content)->implode("\n");

        expect($request->model())->toBe('claude-sonnet-5-5')
            ->and($system)->toContain('MY-PLAYBOOK')
            ->and($request->prompt())->toContain("<job_description>\nJD-TEXT\n</job_description>")
            ->and($request->prompt())->toContain('# Jane Doe MASTER')
            ->and($request->prompt())->toContain('Brazilian Portuguese (pt-BR)');
    });
});

it('picks the CV convention from the document language and the profile', function (Language $language, ?CvConvention $choice, string $expected, string $notExpected): void {
    $generation = queuedGeneration(['language' => $language]);
    $generation->application->user->candidateProfile->forceFill(['cv_convention' => $choice])->save();
    $fake = Prism::fake([fakeGenerationResponse(generationAnswer())]);

    app(RunGeneration::class)->handle($generation->fresh());

    $fake->assertRequest(fn (array $requests) => expect($requests[0]->prompt())
        ->toContain($expected)
        ->not->toContain($notExpected));
})->with([
    'pt-BR always follows Brazil' => [Language::PtBr, CvConvention::UnitedStates, '## CV conventions: Brazil', '## CV conventions: United States'],
    'English follows the choice' => [Language::En, CvConvention::UnitedStates, '## CV conventions: United States', '## CV conventions: Brazil'],
    'English defaults to International' => [Language::En, null, '## CV conventions: International / EU', '## CV conventions: United States'],
]);

it('keeps a later status the user chose', function (): void {
    $generation = queuedGeneration(['status' => ApplicationStatus::Applied]);
    Prism::fake([fakeGenerationResponse(generationAnswer())]);

    app(RunGeneration::class)->handle($generation);

    expect($generation->application->fresh()->status)->toBe(ApplicationStatus::Applied);
});

it('runs a generation only once, even when the job is delivered twice', function (): void {
    $generation = queuedGeneration();
    $fake = Prism::fake([fakeGenerationResponse(generationAnswer()), fakeGenerationResponse(generationAnswer())]);

    app(RunGeneration::class)->handle($generation);
    app(RunGeneration::class)->handle($generation->fresh());

    $fake->assertCallCount(1);
    expect(GeneratedDocument::query()->count())->toBe(1);
});

it('fails with the provider message the user can act on, saving nothing', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']], 401)]);
    $generation = queuedGeneration();

    app(RunGeneration::class)->handle($generation);

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error)->toBe('Anthropic rejected the API key. Check that it is correct and still active, then save it again. Details from Anthropic: “invalid x-api-key”')
        ->and($generation->error)->not->toContain('sk-ant')
        ->and(GeneratedDocument::query()->count())->toBe(0)
        ->and($generation->application->fresh()->current_generation_id)->toBeNull();
});

it('fails with a generic message when the answer breaks the output contract', function (array $answer): void {
    $generation = queuedGeneration(['wants_cover_letter' => true]);
    Prism::fake([fakeGenerationResponse($answer)]);

    app(RunGeneration::class)->handle($generation);

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error)->toBe('A service we rely on isn’t responding right now. Please try again in a few minutes.')
        ->and(GeneratedDocument::query()->count())->toBe(0);
})->with([
    'no CV' => [array_diff_key(generationAnswer(['cover_letter' => 'x']), ['cv_markdown' => true])],
    'no requested cover letter' => [generationAnswer()],
    'broken report' => [generationAnswer(['cover_letter' => 'x', 'report' => ['matches' => 'nope']])],
]);

it('fails clearly when the key used for the generation was deleted meanwhile', function (): void {
    $generation = queuedGeneration();
    $generation->application->user->aiCredentials()->delete();
    $fake = Prism::fake();

    app(RunGeneration::class)->handle($generation);

    $fake->assertCallCount(0);
    expect($generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->fresh()->error)->toContain('Anthropic key for this generation was deleted');
});

it('writes failure messages in the user language', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'x']], 401)]);
    $generation = queuedGeneration(user: User::factory()->create(['locale' => Language::PtBr]));

    (new RunGenerationJob($generation))->handle(app(RunGeneration::class));

    expect($generation->fresh()->error)->toBe('Anthropic recusou a chave de API. Verifique se ela está correta e ativa e salve-a novamente. Detalhes da Anthropic: “x”');
});

it('marks the generation failed when the job dies mid-run', function (): void {
    $generation = queuedGeneration();
    $generation->start(now()->toImmutable());

    (new RunGenerationJob($generation))->failed(new RuntimeException('Worker timed out'));

    expect($generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->fresh()->error)->not->toContain('Worker timed out');
});

it('keeps the 3 newest versions and deletes older ones with their PDFs', function (): void {
    $generation = queuedGeneration();
    $application = $generation->application;
    $generation->update(['version' => 4]);
    $old = collect([1, 2, 3])->map(fn (int $version) => Generation::factory()->for($application)->version($version)->create());
    $oldCv = GeneratedDocument::factory()->for($old[0])->create(['type' => DocumentType::Cv]);
    $oldPdf = StoredFile::factory()->forGeneratedDocument($oldCv, $application->user)->create();
    Storage::disk('r2')->put($oldPdf->path, '%PDF');
    Prism::fake([fakeGenerationResponse(generationAnswer())]);

    app(RunGeneration::class)->handle($generation);

    expect($application->generations()->orderBy('version')->pluck('version')->all())->toBe([2, 3, 4])
        ->and(StoredFile::query()->count())->toBe(0);
    Storage::disk('r2')->assertMissing($oldPdf->path);
});

it('never prunes the version the application shows, or one still running', function (): void {
    $application = Application::factory()->create();
    $current = Generation::factory()->for($application)->version(1)->create();
    $application->adoptGeneration($current);
    Generation::factory()->for($application)->version(2)->running()->create();
    foreach ([3, 4, 5] as $version) {
        Generation::factory()->for($application)->version($version)->failed()->create();
    }

    app(PruneGenerations::class)->handle($application);

    expect($application->generations()->orderBy('version')->pluck('version')->all())->toBe([1, 2, 3, 4, 5]);
});
