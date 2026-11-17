<?php

namespace App\Http\Requests\Settings;

use App\Enums\CvConvention;
use App\Enums\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateProfileRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:120'],
            'work_authorization' => ['nullable', 'string', 'max:160'],
            'cv_convention' => ['nullable', Rule::in(array_map(static fn (CvConvention $convention): string => $convention->value, CvConvention::forEnglish()))],
            'links' => ['nullable', 'array:linkedin,github,portfolio'],
            'links.linkedin' => ['nullable', 'url:http,https', 'max:255'],
            'links.github' => ['nullable', 'url:http,https', 'max:255'],
            'links.portfolio' => ['nullable', 'url:http,https', 'max:255'],
            'default_output_language' => ['required', Rule::enum(Language::class)],
        ];
    }

    /** @return array{linkedin: string|null, github: string|null, portfolio: string|null} */
    public function links(): array
    {
        return [
            'linkedin' => $this->string('links.linkedin')->trim()->toString() ?: null,
            'github' => $this->string('links.github')->trim()->toString() ?: null,
            'portfolio' => $this->string('links.portfolio')->trim()->toString() ?: null,
        ];
    }

    public function optional(string $key): ?string
    {
        return $this->string($key)->trim()->toString() ?: null;
    }
}
