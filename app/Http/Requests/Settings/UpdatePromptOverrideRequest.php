<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromptOverrideRequest extends FormRequest
{
    public const int MAX_CHARACTERS = 30_000;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.self::MAX_CHARACTERS],
        ];
    }
}
