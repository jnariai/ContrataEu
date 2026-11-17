<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Models\GeneratedAnswer;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\ValueObjects\CvMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One generation for the show page. Callers eager-load `documents` and `answers.screeningQuestion`.
 *
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'version' => $this->version,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label(), 'finished' => $this->status->isFinished()],
            'language' => ['value' => $this->language->value, 'label' => $this->language->label()],
            'provider' => $this->provider->label(),
            'model' => $this->model,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601ZuluString(),
            'finished_at' => $this->finished_at?->toIso8601ZuluString(),
            'report' => $this->report === null ? null : [
                ...$this->report->toArray(),
                'keyword_coverage' => $this->report->keywordCoverage->jsonSerialize(),
            ],
            'documents' => $this->documents
                ->sortBy(fn (GeneratedDocument $document): int => $document->type->sortOrder())
                ->map(fn (GeneratedDocument $document): array => [
                    'id' => $document->public_id,
                    'type' => $document->type->value,
                    'label' => $document->type->label(),
                    'content' => $document->content,
                    'has_pdf' => $document->type->hasPdf(),
                    'edited' => $document->isEdited(),
                    'updated_at' => $document->updated_at->toIso8601ZuluString(),
                    'todos' => CvMarkdown::fromString($document->content)->todos(),
                    'warnings' => $document->type === DocumentType::Cv
                        ? CvMarkdown::fromString($document->content)->warnings($this->language)
                        : [],
                ])->values(),
            'answers' => $this->answers
                ->sortBy(fn (GeneratedAnswer $answer): int => $answer->screeningQuestion->position)
                ->map(fn (GeneratedAnswer $answer): array => [
                    'id' => $answer->public_id,
                    'question' => $answer->screeningQuestion->question,
                    'content' => $answer->content,
                    'edited' => $answer->isEdited(),
                    'updated_at' => $answer->updated_at->toIso8601ZuluString(),
                    'todos' => CvMarkdown::fromString($answer->content)->todos(),
                ])->values(),
        ];
    }
}
