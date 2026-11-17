<?php

use App\Enums\DocumentType;
use App\Enums\Language;
use App\Enums\WorkMode;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\GeneratedAnswer;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\ScreeningQuestion;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

function validApplication(array $overrides = []): array
{
    return [
        'company_name' => 'Acme',
        'role_title' => 'Senior Backend Engineer',
        'job_url' => 'https://jobs.acme.test/123',
        'location' => 'Lisbon',
        'work_mode' => 'remote',
        'language' => 'pt-BR',
        'company_notes' => 'Fintech, 50 people.',
        'job_description' => 'We need Laravel and PostgreSQL.',
        'wants_cover_letter' => '1',
        'questions' => [['id' => null, 'question' => 'Why Acme?'], ['id' => null, 'question' => 'Notice period?']],
        ...$overrides,
    ];
}

it('prefills the output language from the candidate profile', function (): void {
    $user = User::factory()->create();
    CandidateProfile::factory()->for($user)->create(['default_output_language' => Language::PtBr]);

    $this->actingAs($user)->get(route('applications.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applications/create')
            ->where('defaultLanguage', 'pt-BR')
            ->has('workModes', 3));
});

it('creates a draft application with its screening questions in order', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('applications.store'), validApplication());

    $application = Application::query()->sole();
    $response->assertRedirect(route('applications.show', $application))->assertInertiaFlash('toast.type', 'success');
    expect($application->user_id)->toBe($user->id)
        ->and($application->status->value)->toBe('draft')
        ->and($application->language)->toBe(Language::PtBr)
        ->and($application->work_mode)->toBe(WorkMode::Remote)
        ->and($application->wants_cover_letter)->toBeTrue()
        ->and($application->wants_recruiter_message)->toBeFalse()
        ->and($application->screeningQuestions()->pluck('question')->all())->toBe(['Why Acme?', 'Notice period?'])
        ->and($application->screeningQuestions()->pluck('position')->all())->toBe([1, 2]);
});

it('validates the application', function (array $input, string $field): void {
    $this->actingAs(User::factory()->create())
        ->post(route('applications.store'), validApplication($input))
        ->assertSessionHasErrors($field);
})->with([
    'company required' => [['company_name' => ''], 'company_name'],
    'role required' => [['role_title' => ''], 'role_title'],
    'job description required' => [['job_description' => ''], 'job_description'],
    'job description max 20k' => [['job_description' => str_repeat('a', 20_001)], 'job_description'],
    'supported language' => [['language' => 'es'], 'language'],
    'known work mode' => [['work_mode' => 'mars'], 'work_mode'],
    'http job url' => [['job_url' => 'ftp://jobs.test'], 'job_url'],
    'question text required' => [['questions' => [['question' => '']]], 'questions.0.question'],
    'at most 10 questions' => [['questions' => array_fill(0, 11, ['question' => 'Q?'])], 'questions'],
    'no question ids on create' => [['questions' => [['id' => 'q_1111111111111111111111', 'question' => 'Q?']]], 'questions.0.id'],
]);

it('shows the application to its owner without internal ids', function (): void {
    $application = Application::factory()->create();
    $question = ScreeningQuestion::factory()->for($application)->create();

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('applications/show')
            ->where('application.id', $application->public_id)
            ->where('application.questions.0.id', $question->public_id)
            ->missing('application.user_id')
            ->where('can.update', true));
});

it('keeps reworded questions, adds new ones and deletes removed ones with their answers', function (): void {
    $application = Application::factory()->create();
    $kept = ScreeningQuestion::factory()->for($application)->create(['question' => 'Why us?', 'position' => 1]);
    $removed = ScreeningQuestion::factory()->for($application)->create(['question' => 'Salary?', 'position' => 2]);
    $generation = Generation::factory()->for($application)->create();
    $keptAnswer = GeneratedAnswer::factory()->for($generation)->for($kept)->create();
    GeneratedAnswer::factory()->for($generation)->for($removed)->create();

    $this->actingAs($application->user)
        ->put(route('applications.update', $application), validApplication(['questions' => [
            ['id' => null, 'question' => 'Notice period?'],
            ['id' => $kept->public_id, 'question' => 'Why do you want to join us?'],
        ]]))
        ->assertRedirect(route('applications.show', $application));

    $questions = $application->screeningQuestions()->get();
    expect($questions->pluck('question')->all())->toBe(['Notice period?', 'Why do you want to join us?'])
        ->and($questions->last()->id)->toBe($kept->id)
        ->and(ScreeningQuestion::query()->find($removed->id))->toBeNull()
        ->and(GeneratedAnswer::query()->pluck('id')->all())->toBe([$keptAnswer->id]);
});

it("refuses another application's question id", function (): void {
    $application = Application::factory()->create();
    $foreign = ScreeningQuestion::factory()->create();

    $this->actingAs($application->user)
        ->put(route('applications.update', $application), validApplication(['questions' => [['id' => $foreign->public_id, 'question' => 'Hijack?']]]))
        ->assertSessionHasErrors('questions.0.id');

    expect($foreign->fresh()->question)->not->toBe('Hijack?');
});

it('leaves existing generations alone when the application changes', function (): void {
    $application = Application::factory()->create(['language' => Language::En]);
    $generation = Generation::factory()->for($application)->create(['language' => Language::En]);

    $this->actingAs($application->user)->put(route('applications.update', $application), validApplication(['language' => 'pt-BR']));

    expect($application->fresh()->language)->toBe(Language::PtBr)
        ->and($generation->fresh()->language)->toBe(Language::En);
});

it('deletes the application with its generations and their PDFs in the bucket', function (): void {
    $application = Application::factory()->create();
    $document = GeneratedDocument::factory()->for(Generation::factory()->for($application))->create(['type' => DocumentType::Cv]);
    $pdf = StoredFile::factory()->forGeneratedDocument($document, $application->user)->create();
    Storage::disk('r2')->put($pdf->path, '%PDF');
    $other = Application::factory()->for($application->user)->create();

    $this->actingAs($application->user)->delete(route('applications.destroy', $application))
        ->assertRedirect(route('dashboard'));

    expect(Application::query()->pluck('id')->all())->toBe([$other->id])
        ->and(Generation::query()->count())->toBe(0)
        ->and(StoredFile::query()->count())->toBe(0);
    Storage::disk('r2')->assertMissing($pdf->path);
});

it("answers 404 for another user's application", function (string $method, string $route): void {
    $application = Application::factory()->create();

    $this->actingAs(User::factory()->create())->{$method}(route($route, $application), validApplication())->assertNotFound();

    expect($application->fresh())->not->toBeNull()->and($application->fresh()->company_name)->not->toBe('Acme');
})->with([
    'show' => ['get', 'applications.show'],
    'edit' => ['get', 'applications.edit'],
    'update' => ['put', 'applications.update'],
    'delete' => ['delete', 'applications.destroy'],
]);

it('sends guests to the login page', function (): void {
    $this->get(route('applications.create'))->assertRedirect(route('login'));
});
