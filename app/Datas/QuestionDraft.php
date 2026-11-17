<?php

namespace App\Datas;

/**
 * One screening question as submitted: `id` is the existing question's public_id, or null for a new one.
 */
final readonly class QuestionDraft
{
    public function __construct(
        public ?string $id,
        public string $text,
    ) {}
}
