<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** The (possibly unsaved) text to preview in the PDF template. Nothing is stored. */
class PreviewDocumentRequest extends FormRequest
{
    public const int MAX_CHARACTERS = 50_000;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.self::MAX_CHARACTERS],
        ];
    }
}
