<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\Language;
use App\Enums\WorkMode;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Application> */
class ApplicationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'role_title' => 'Senior Backend Engineer',
            'job_url' => fake()->url(),
            'location' => fake()->city(),
            'work_mode' => WorkMode::Remote,
            'language' => Language::En,
            'company_notes' => null,
            'job_description' => "We are looking for a Senior Backend Engineer.\n\nRequirements:\n- 5+ years of PHP\n- Laravel\n- PostgreSQL",
            'wants_cover_letter' => false,
            'wants_recruiter_message' => false,
            'status' => ApplicationStatus::Draft,
            'applied_at' => null,
        ];
    }

    public function inStatus(ApplicationStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function applied(): static
    {
        return $this->state(['status' => ApplicationStatus::Applied, 'applied_at' => now()]);
    }

    public function inPortuguese(): static
    {
        return $this->state(['language' => Language::PtBr]);
    }

    public function withExtras(): static
    {
        return $this->state(['wants_cover_letter' => true, 'wants_recruiter_message' => true]);
    }
}
