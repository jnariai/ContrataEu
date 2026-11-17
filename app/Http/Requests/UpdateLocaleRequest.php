<?php

namespace App\Http\Requests;

use App\Enums\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::enum(Language::class)],
        ];
    }

    public function language(): Language
    {
        return Language::from($this->string('locale')->toString());
    }
}
