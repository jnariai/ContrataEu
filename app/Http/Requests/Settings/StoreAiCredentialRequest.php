<?php

namespace App\Http\Requests\Settings;

use App\Enums\AiProvider;
use App\Gateways\Prism\PublicEndpoint;
use App\Models\AiCredential;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiCredentialRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(PublicEndpoint $publicEndpoint): array
    {
        $provider = AiProvider::tryFrom($this->string('provider')->toString());

        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'api_key' => ['required', 'string', 'min:10', 'max:500', 'regex:/^\S+$/'],
            'base_url' => self::baseUrlRules($provider?->needsBaseUrl() === true, $publicEndpoint),
            'model' => [Rule::requiredIf($provider !== null && $provider->defaultModel() === null), 'nullable', 'string', 'max:200', 'regex:/^[\w.:\/@-]+$/'],
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
            ...self::baseUrlMessages(),
        ];
    }

    public function provider(): AiProvider
    {
        return AiProvider::from($this->string('provider')->toString());
    }

    /**
     * An OpenAI-compatible key's address: required, and a public HTTPS server, because our server calls it. Other
     * providers have a fixed address, so the field is dropped for them.
     *
     * @return list<mixed>
     */
    public static function baseUrlRules(bool $needsBaseUrl, PublicEndpoint $publicEndpoint): array
    {
        return [
            Rule::excludeIf(! $needsBaseUrl),
            'bail', 'required', 'string', 'max:255', 'url:https',
            function (string $attribute, mixed $value, Closure $fail) use ($publicEndpoint): void {
                if ($publicEndpoint->addressFor((string) $value) === null) {
                    $fail(__('This address isn’t a public server we can reach. Check it, and note that local and private network addresses can’t be used.'));
                }
            },
        ];
    }

    /** @return array<string, string> */
    public static function baseUrlMessages(): array
    {
        return [
            'base_url.required' => __('Enter the address of the server, such as https://api.example.com/v1.'),
            'base_url.url' => __('Enter the full address, starting with https://.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('base_url'))) {
            $this->merge(['base_url' => AiCredential::cleanBaseUrl($this->input('base_url'))]);
        }
    }
}
