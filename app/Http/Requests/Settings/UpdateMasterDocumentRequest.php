<?php

namespace App\Http\Requests\Settings;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
