<?php

namespace App\Http\Requests\Settings;

use App\Gateways\Prism\PublicEndpoint;
use App\Models\AiCredential;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renaming a saved key or changing its model (and an OpenAI-compatible key's address); the key itself can't be
 * changed (add a new one instead).
 */
class UpdateAiCredentialRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(PublicEndpoint $publicEndpoint): array
    {
        return [
            'model' => ['required', 'string', 'max:200', 'regex:/^[\w.:\/@-]+$/'],
            'base_url' => StoreAiCredentialRequest::baseUrlRules($this->credential()->provider->needsBaseUrl(), $publicEndpoint),
            'name' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'model.regex' => __('Use the model id exactly as your provider lists it, without spaces.'),
            ...StoreAiCredentialRequest::baseUrlMessages(),
        ];
    }

    public function credential(): AiCredential
    {
        /** @var AiCredential */
        return $this->route('aiCredential');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['model' => trim((string) $this->input('model'))]);

        if (is_string($this->input('base_url'))) {
            $this->merge(['base_url' => AiCredential::cleanBaseUrl($this->input('base_url'))]);
        }
    }
}
