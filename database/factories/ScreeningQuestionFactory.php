<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ScreeningQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScreeningQuestion> */
class ScreeningQuestionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'question' => fake()->randomElement(['Why do you want to work with us?', 'What is your notice period?', 'Describe a hard bug you fixed.']),
            'position' => 1,
        ];
    }
}
