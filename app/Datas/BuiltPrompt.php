<?php

namespace App\Datas;

/**
 * The final prompt, plus which optional outputs the app-owned output contract asks for.
 * The JSON schema itself is built from these flags by the AI gateway.
 */
final readonly class BuiltPrompt
{
    public function __construct(
        public string $system,
        public string $user,
        public bool $wantsCoverLetter,
        public bool $wantsRecruiterMessage,
        public int $questionCount,
    ) {}
}
