<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Models\Application;
use App\Models\Generation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Generation> */
class GenerationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'version' => 1,
            'status' => GenerationStatus::Succeeded,
            'provider' => AiProvider::Anthropic,
            'model' => AiProvider::Anthropic->defaultModel(),
            'language' => Language::En,
            'report' => null,
            'input_tokens' => 1200,
            'output_tokens' => 2400,
            'error' => null,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(['status' => GenerationStatus::Queued, 'started_at' => null, 'finished_at' => null, 'input_tokens' => null, 'output_tokens' => null]);
    }

    public function running(): static
    {
        return $this->state(['status' => GenerationStatus::Running, 'started_at' => now(), 'finished_at' => null, 'input_tokens' => null, 'output_tokens' => null]);
    }

    public function failed(): static
    {
        return $this->state(['status' => GenerationStatus::Failed, 'error' => 'The AI provider rejected the request.', 'input_tokens' => null, 'output_tokens' => null]);
    }

    public function version(int $version): static
    {
        return $this->state(['version' => $version]);
    }
}
