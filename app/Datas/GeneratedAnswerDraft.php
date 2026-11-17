<?php

namespace App\Datas;

/** The AI's answer to the screening question at `questionNumber` (1-based, in the order they were sent). */
final readonly class GeneratedAnswerDraft
{
    public function __construct(
        public int $questionNumber,
        public string $answer,
    ) {}
}
