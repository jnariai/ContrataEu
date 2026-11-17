<?php

namespace App\Http\Requests;

use App\Models\GeneratedAnswer;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateGeneratedAnswerRequest extends FormRequest
{
    public const int MAX_CHARACTERS = 5_000;

    /** Returning the policy's response keeps its 404 for other users' answers. */
    public function authorize(): Response
    {
        /** @var GeneratedAnswer $answer */
        $answer = $this->route('answer');

        return Gate::inspect('update', $answer);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.self::MAX_CHARACTERS],
        ];
    }
}
