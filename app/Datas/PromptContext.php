<?php

namespace App\Datas;

use App\Enums\CvConvention;
use App\Enums\Language;

/**
 * Everything one generation's prompt is built from, gathered once: the candidate, their master documents,
 * the job, and the resolved prompt sections (the user's override, or the app default).
 */
final readonly class PromptContext
{
    /**
     * @param  array<string, string>  $candidate  label => value, only filled ones
     * @param  array<string, string>  $job  label => value, only filled ones
     * @param  list<string>  $questions  in order
     */
    public function __construct(
        public array $candidate,
        public string $masterCv,
        public ?string $masterCoverLetter,
        public ?string $recruiterMessageInspiration,
        public array $job,
        public string $jobDescription,
        public array $questions,
        public bool $wantsCoverLetter,
        public bool $wantsRecruiterMessage,
        public Language $documentLanguage,
        public CvConvention $cvConvention,
        public Language $reportLanguage,
        public string $system,
        public string $playbook,
        public string $cvInstructions,
        public string $coverLetterInstructions,
        public string $recruiterMessageInstructions,
        public string $screeningAnswerInstructions,
    ) {}
}
