<?php

namespace App\Http\Requests\Settings;

use App\Enums\AiProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiCredentialRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'api_key' => ['required', 'string', 'min:10', 'max:500', 'regex:/^\S+$/'],
            'model' => ['nullable', 'string', 'max:200', 'regex:/^[\w.:\/@-]+$/'],
            'name' => ['nullable', 'string', 'max:60'],
            'activate' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'api_key.regex' => __('The API key can’t contain spaces.'),
            'model.regex' => __('Use the model id exactly as your provider lists it, without spaces.'),
        ];
    }

    public function provider(): AiProvider
    {
        return AiProvider::from($this->string('provider')->toString());
    }
}
