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
