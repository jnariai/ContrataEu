<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One lookup table per stored enum. Ids are explicit and never change; the enum in app/Enums is the source of truth.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> table => values, in id order starting at 1 */
    private array $lookups = [
        'document_types' => ['cv', 'cover_letter', 'recruiter_message'],
        'prompt_keys' => ['system', 'playbook', 'cv_instructions', 'cover_letter_instructions', 'recruiter_message_instructions', 'screening_answer_instructions'],
        'application_statuses' => ['draft', 'ready', 'applied', 'interviewing', 'offer', 'rejected', 'withdrawn'],
        'generation_statuses' => ['queued', 'running', 'succeeded', 'failed'],
        'ai_providers' => ['anthropic', 'gemini', 'openrouter', 'groq'],
        'work_modes' => ['remote', 'hybrid', 'onsite'],
        'file_purposes' => ['master_original', 'document_pdf'],
    ];

    public function up(): void
    {
        foreach ($this->lookups as $table => $values) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedSmallInteger('id')->primary();
                $blueprint->string('value', 64)->unique();
            });

            DB::table($table)->insert(array_map(
                static fn (string $value, int $index): array => ['id' => $index + 1, 'value' => $value],
                $values,
                array_keys($values),
            ));
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys($this->lookups)) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
