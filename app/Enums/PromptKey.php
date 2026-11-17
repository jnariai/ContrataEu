<?php

namespace App\Enums;

/**
 * A user-overridable section of the generation prompt. The output contract (JSON schema, CV source structure,
 * hard rules, language rules) is app-owned and has no key, so it can't be overridden.
 */
enum PromptKey: string
{
    case System = 'system';
    case Playbook = 'playbook';
    case CvInstructions = 'cv_instructions';
    case CoverLetterInstructions = 'cover_letter_instructions';
    case RecruiterMessageInstructions = 'recruiter_message_instructions';
    case ScreeningAnswerInstructions = 'screening_answer_instructions';

    public function label(): string
    {
        return match ($this) {
            self::System => __('System prompt'),
            self::Playbook => __('Screening & writing playbook'),
            self::CvInstructions => __('CV instructions'),
            self::CoverLetterInstructions => __('Cover letter instructions'),
            self::RecruiterMessageInstructions => __('Recruiter message instructions'),
            self::ScreeningAnswerInstructions => __('Screening answer instructions'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::System => __('Who the AI is and how it treats the job description.'),
            self::Playbook => __('Screening and writing rules every document must follow.'),
            self::CvInstructions => __('How to tailor the CV for the job.'),
            self::CoverLetterInstructions => __('How to write the cover letter, when you ask for one.'),
            self::RecruiterMessageInstructions => __('How to write the message to the recruiter, when you ask for one.'),
            self::ScreeningAnswerInstructions => __('How to answer the job’s screening questions.'),
        };
    }

    /** The app's default text for this section (a Markdown file shipped with the app). */
    public function defaultContent(): string
    {
        return (string) file_get_contents($this->defaultPath());
    }

    public function defaultPath(): string
    {
        return resource_path('prompts/'.str_replace('_', '-', $this->value).'.md');
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
