<?php

namespace App\Datas;

use App\Enums\MatchStrength;

/** One job requirement and the candidate's evidence for it. */
final readonly class RequirementMatch
{
    public function __construct(
        public string $requirement,
        public string $evidence,
        public MatchStrength $strength,
    ) {}
}
