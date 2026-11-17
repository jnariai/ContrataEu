<?php

namespace App\Http\Requests\Settings;

use App\Enums\FilePurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCvImportRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            // `mimes` checks the file's content, not its name. PDF only: that's what the AI providers can read.
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.FilePurpose::MasterOriginal->maxKilobytes()],
        ];
    }
}
