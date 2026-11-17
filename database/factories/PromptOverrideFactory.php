<?php

namespace Database\Factories;

use App\Enums\PromptKey;
use App\Models\PromptOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PromptOverride> */
class PromptOverrideFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => PromptKey::Playbook,
            'content' => 'My own playbook: '.fake()->sentence(),
        ];
    }
}
