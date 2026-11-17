<?php

use App\Enums\AiProvider;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Enums\PromptKey;
use App\Enums\WorkMode;
use Illuminate\Support\Facades\DB;

it('has exactly one lookup row per enum case', function (string $enum, string $table): void {
    expect(DB::table($table)->orderBy('value')->pluck('value')->all())
        ->toBe(collect($enum::cases())->map(fn (BackedEnum $case): string => (string) $case->value)->sort()->values()->all());
})->with([
    'languages' => [Language::class, 'languages'],
    'document types' => [DocumentType::class, 'document_types'],
    'prompt keys' => [PromptKey::class, 'prompt_keys'],
    'application statuses' => [ApplicationStatus::class, 'application_statuses'],
    'generation statuses' => [GenerationStatus::class, 'generation_statuses'],
    'ai providers' => [AiProvider::class, 'ai_providers'],
    'work modes' => [WorkMode::class, 'work_modes'],
    'file purposes' => [FilePurpose::class, 'file_purposes'],
]);

it('keeps the generation status ids the partial unique index relies on', function (): void {
    expect(DB::table('generation_statuses')->whereIn('id', [1, 2])->orderBy('id')->pluck('value')->all())
        ->toBe([GenerationStatus::Queued->value, GenerationStatus::Running->value]);
});
