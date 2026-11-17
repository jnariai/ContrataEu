<?php

namespace App\Http\Requests\Settings;

use App\Casts\LookupCast;
use App\Enums\DocumentType;
use App\Enums\GenerationStatus;
use App\Models\CvImport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterDocumentRequest extends FormRequest
{
    public const int MAX_CHARACTERS = 50_000;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var DocumentType $type */
        $type = $this->route('type');

        return [
            'content' => [$type->isRequired() ? 'required' : 'nullable', 'string', 'max:'.self::MAX_CHARACTERS],
            // The PDF import whose result is being saved: only the CV, only the user's own finished import.
            'import' => [
                Rule::prohibitedIf($type !== DocumentType::Cv),
                'nullable',
                'string',
                Rule::exists('cv_imports', 'public_id')
                    ->where('user_id', $this->user()?->id)
                    ->where('status_id', LookupCast::idOf('generation_statuses', GenerationStatus::Succeeded)),
            ],
        ];
    }

    public function cvImport(): ?CvImport
    {
        return $this->filled('import')
            ? CvImport::query()->where('public_id', $this->string('import')->toString())->whereBelongsTo($this->user())->first()
            : null;
    }
}
