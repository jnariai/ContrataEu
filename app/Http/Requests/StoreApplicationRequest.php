<?php

namespace App\Http\Requests;

use App\Datas\ApplicationDetails;
use App\Datas\QuestionDraft;
use App\Enums\Language;
use App\Enums\WorkMode;
use App\Models\Application;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public const int MAX_JOB_DESCRIPTION = 20_000;

    public const int MAX_QUESTIONS = 10;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'role_title' => ['required', 'string', 'max:160'],
            'job_url' => ['nullable', 'url:http,https', 'max:2048'],
            'location' => ['nullable', 'string', 'max:120'],
            'work_mode' => ['nullable', Rule::enum(WorkMode::class)],
            'language' => ['required', Rule::enum(Language::class)],
            'company_notes' => ['nullable', 'string', 'max:5000'],
            'job_description' => ['required', 'string', 'max:'.self::MAX_JOB_DESCRIPTION],
            'wants_cover_letter' => ['sometimes', 'boolean'],
            'wants_recruiter_message' => ['sometimes', 'boolean'],
            'questions' => ['nullable', 'array', 'max:'.self::MAX_QUESTIONS],
            'questions.*' => ['array:id,question'],
            'questions.*.id' => ['nullable', 'string', $this->existingQuestionRule()],
            'questions.*.question' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['questions.*.question' => __('question')];
    }

    public function toData(): ApplicationDetails
    {
        return new ApplicationDetails(
            companyName: $this->string('company_name')->trim()->toString(),
            roleTitle: $this->string('role_title')->trim()->toString(),
            jobUrl: $this->optional('job_url'),
            location: $this->optional('location'),
            workMode: $this->filled('work_mode') ? WorkMode::from($this->string('work_mode')->toString()) : null,
            language: Language::from($this->string('language')->toString()),
            companyNotes: $this->optional('company_notes'),
            jobDescription: $this->string('job_description')->trim()->toString(),
            wantsCoverLetter: $this->boolean('wants_cover_letter'),
            wantsRecruiterMessage: $this->boolean('wants_recruiter_message'),
            questions: array_map(
                static fn (array $question): QuestionDraft => new QuestionDraft(
                    id: filled($question['id'] ?? null) ? (string) $question['id'] : null,
                    text: trim((string) $question['question']),
                ),
                array_values((array) $this->validated('questions', [])),
            ),
        );
    }

    /** A new application has no questions yet, so any id is foreign. */
    protected function existingQuestionRule(): mixed
    {
        return 'prohibited';
    }

    private function optional(string $key): ?string
    {
        return $this->string($key)->trim()->toString() ?: null;
    }

    protected function application(): ?Application
    {
        $application = $this->route('application');

        return $application instanceof Application ? $application : null;
    }
}
