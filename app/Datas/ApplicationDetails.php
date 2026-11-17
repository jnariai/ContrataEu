<?php

namespace App\Datas;

use App\Enums\Language;
use App\Enums\WorkMode;

/**
 * What the user typed about a job: details, job description, options and screening questions.
 */
final readonly class ApplicationDetails
{
    /**
     * @param  list<QuestionDraft>  $questions  in the order the user wants them
     */
    public function __construct(
        public string $companyName,
        public string $roleTitle,
        public ?string $jobUrl,
        public ?string $location,
        public ?WorkMode $workMode,
        public Language $language,
        public ?string $companyNotes,
        public string $jobDescription,
        public bool $wantsCoverLetter,
        public bool $wantsRecruiterMessage,
        public array $questions,
    ) {}
}
