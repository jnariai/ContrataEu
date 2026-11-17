<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GeneratedDocument> */
class GeneratedDocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $content = "# Jane Doe\n\nSenior Backend Engineer · Lisbon\n\n## Experience\n\n### Backend Engineer, Acme\n\n*Mar 2022 – Present*\n\n- Cut billing run time by 60% with queued Laravel jobs.\n";

        return [
            'generation_id' => Generation::factory(),
            'type' => DocumentType::Cv,
            'content' => $content,
            'original_content' => $content,
            'edited_at' => null,
        ];
    }

    public function ofType(DocumentType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function edited(string $content = 'Edited by the user.'): static
    {
        return $this->state(['content' => $content, 'edited_at' => now()]);
    }
}
