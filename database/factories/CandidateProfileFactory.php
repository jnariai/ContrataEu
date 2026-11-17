<?php

namespace Database\Factories;

use App\Enums\Language;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CandidateProfile> */
class CandidateProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'headline' => 'Senior Backend Engineer',
            'location' => fake()->city().', '.fake()->country(),
            'work_authorization' => 'EU citizen',
            'cv_convention' => null,
            'links' => [
                'linkedin' => 'https://www.linkedin.com/in/'.fake()->userName(),
                'github' => 'https://github.com/'.fake()->userName(),
                'portfolio' => null,
            ],
            'default_output_language' => Language::En,
        ];
    }
}
