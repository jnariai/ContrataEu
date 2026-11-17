<?php

namespace App\Http\Requests\Settings;

use App\Enums\FilePurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMasterDocumentFileRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $purpose = FilePurpose::MasterOriginal;

        return [
            // `mimes` checks the file's content, not its name.
            'file' => ['required', 'file', 'mimes:'.implode(',', $purpose->allowedMimes()), 'max:'.$purpose->maxKilobytes()],
        ];
    }
}
