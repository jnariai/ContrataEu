<?php

namespace App\Datas;

/** Everything one AI run produced, already checked against the app-owned output contract. */
final readonly class GenerationOutput
{
    /** @param list<GeneratedAnswerDraft> $answers */
    public function __construct(
        public GenerationReport $report,
        public string $cvMarkdown,
        public ?string $coverLetter,
        public ?string $recruiterMessage,
        public array $answers,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
