<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Changing the model of a saved key; the key itself can't be changed (add a new one instead). */
class UpdateAiCredentialRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'max:200', 'regex:/^[\w.:\/@-]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'model.regex' => __('Use the model id exactly as your provider lists it, without spaces.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['model' => trim((string) $this->input('model'))]);
    }
}
