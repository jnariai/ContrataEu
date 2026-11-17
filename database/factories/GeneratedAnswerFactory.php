<?php

namespace Database\Factories;

use App\Models\GeneratedAnswer;
use App\Models\Generation;
use App\Models\ScreeningQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GeneratedAnswer> */
class GeneratedAnswerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $answer = fake()->paragraph();

        return [
            'generation_id' => Generation::factory(),
            'screening_question_id' => ScreeningQuestion::factory(),
            'content' => $answer,
            'original_content' => $answer,
            'edited_at' => null,
        ];
    }
}
