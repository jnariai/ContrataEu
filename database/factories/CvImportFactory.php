<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Enums\GenerationStatus;
use App\Models\CvImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CvImport> */
class CvImportFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => GenerationStatus::Succeeded,
            'provider' => AiProvider::Anthropic,
            'model' => AiProvider::Anthropic->defaultModel(),
            'markdown' => "# Jane Doe\nLisbon · jane@example.com\n\n## Summary\nBackend engineer.",
            'error' => null,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(['status' => GenerationStatus::Queued, 'markdown' => null, 'started_at' => null, 'finished_at' => null]);
    }

    public function failed(): static
    {
        return $this->state(['status' => GenerationStatus::Failed, 'markdown' => null, 'error' => 'The AI provider rejected the request.']);
    }
}
