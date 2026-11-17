<?php

use App\Enums\ApplicationStatus;
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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('builds a valid model from every factory', function (string $model): void {
    expect($model::factory()->create()->exists)->toBeTrue();
})->with([
    CandidateProfile::class, MasterDocument::class, PromptOverride::class, AiCredential::class, Application::class,
    ScreeningQuestion::class, Generation::class, GeneratedDocument::class, GeneratedAnswer::class, StoredFile::class,
]);

it('gives exposed models a prefixed Base58 public id used as the route key', function (): void {
    $application = Application::factory()->create();

    expect($application->public_id)->toMatch('/^app_[1-9A-HJ-NP-Za-km-z]{22}$/')
        ->and($application->getRouteKey())->toBe($application->public_id);
});

it('never changes a public id once it exists', function (): void {
    $application = Application::factory()->create();
    $application->public_id = 'app_1111111111111111111111';

    expect(fn () => $application->save())->toThrow(LogicException::class);
});

it('stores the API key encrypted and hides it from serialization', function (): void {
    $credential = AiCredential::factory()->create(['api_key' => 'sk-ant-plain-secret-a1b2', 'key_hint' => 'a1b2']);

    expect(DB::table('ai_credentials')->where('id', $credential->id)->value('api_key'))->not->toContain('sk-ant-plain-secret')
        ->and($credential->fresh()->api_key)->toBe('sk-ant-plain-secret-a1b2')
        ->and($credential->toArray())->not->toHaveKey('api_key');
});

it('allows only one default AI credential per user', function (): void {
    $user = User::factory()->create();
    AiCredential::factory()->for($user)->asDefault()->create();

    expect(fn () => AiCredential::factory()->for($user)->asDefault()->create())->toThrow(QueryException::class);
});

it('allows only one queued or running generation per application', function (): void {
    $application = Application::factory()->create();
    Generation::factory()->for($application)->version(1)->running()->create();

    expect(fn () => Generation::factory()->for($application)->version(2)->queued()->create())->toThrow(QueryException::class);
});

it('stores morph types as aliases, not class names', function (): void {
    $file = StoredFile::factory()->forMasterDocument(MasterDocument::factory()->create())->create();

    expect(DB::table('stored_files')->where('id', $file->id)->value('fileable_type'))->toBe('master_document');
});

it('filters applications by status', function (): void {
    Application::factory()->inStatus(ApplicationStatus::Draft)->create();
    $applied = Application::factory()->applied()->create();

    expect(Application::query()->inStatus(ApplicationStatus::Applied)->pluck('id')->all())->toBe([$applied->id]);
});

it('deletes everything a user owns when the user is deleted', function (): void {
    $user = User::factory()->create();
    CandidateProfile::factory()->for($user)->create();
    MasterDocument::factory()->for($user)->create();
    AiCredential::factory()->for($user)->create();
    $application = Application::factory()->for($user)->create();
    $generation = Generation::factory()->for($application)->create();
    GeneratedDocument::factory()->for($generation)->create();

    $user->delete();

    foreach (['candidate_profiles', 'master_documents', 'ai_credentials', 'applications', 'generations', 'generated_documents'] as $table) {
        expect(DB::table($table)->count())->toBe(0, "{$table} should be empty");
    }
});
