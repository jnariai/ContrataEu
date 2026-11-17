<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\ScreeningQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the show and edit pages need about one application. Callers eager-load `screeningQuestions`.
 *
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'company_name' => $this->company_name,
            'role_title' => $this->role_title,
            'job_url' => $this->job_url,
            'location' => $this->location,
            'work_mode' => $this->work_mode === null ? null : ['value' => $this->work_mode->value, 'label' => $this->work_mode->label()],
            'language' => ['value' => $this->language->value, 'label' => $this->language->label()],
            'company_notes' => $this->company_notes,
            'job_description' => $this->job_description,
            'wants_cover_letter' => $this->wants_cover_letter,
            'wants_recruiter_message' => $this->wants_recruiter_message,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label(), 'color' => $this->status->color()],
            'applied_at' => $this->applied_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
            'questions' => $this->screeningQuestions->map(fn (ScreeningQuestion $question): array => [
                'id' => $question->public_id,
                'question' => $question->question,
            ])->values(),
        ];
    }
}
