<?php

namespace App\Http\Requests;

use App\Models\GeneratedDocument;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateGeneratedDocumentRequest extends FormRequest
{
    public const int MAX_CHARACTERS = 50_000;

    /** Returning the policy's response keeps its 404 for other users' documents. */
    public function authorize(): Response
    {
        /** @var GeneratedDocument $document */
        $document = $this->route('document');

        return Gate::inspect('update', $document);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.self::MAX_CHARACTERS],
        ];
    }
}
