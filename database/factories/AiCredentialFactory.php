<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiCredential> */
class AiCredentialFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $key = 'sk-ant-test-'.fake()->regexify('[A-Za-z0-9]{32}');

        return [
            'user_id' => User::factory(),
            'provider' => AiProvider::Anthropic,
            'api_key' => $key,
            'key_hint' => substr($key, -4),
            'model' => AiProvider::Anthropic->defaultModel(),
            'is_default' => false,
            'last_verified_at' => null,
        ];
    }

    public function asDefault(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function verified(): static
    {
        return $this->state(['last_verified_at' => now()]);
    }
}
