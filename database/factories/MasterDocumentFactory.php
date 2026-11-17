<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\MasterDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MasterDocument> */
class MasterDocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => DocumentType::Cv,
            'content' => "# Jane Doe\n\nSenior Backend Engineer · Lisbon · jane@example.com\n\n## Experience\n\n### Backend Engineer, Acme\n\n*Mar 2022 – Present*\n\n- Built the billing platform in Laravel and PostgreSQL.\n",
        ];
    }

    public function coverLetter(): static
    {
        return $this->state(['type' => DocumentType::CoverLetter, 'content' => fake()->paragraphs(3, true)]);
    }

    public function recruiterMessage(): static
    {
        return $this->state(['type' => DocumentType::RecruiterMessage, 'content' => fake()->paragraph()]);
    }
}
