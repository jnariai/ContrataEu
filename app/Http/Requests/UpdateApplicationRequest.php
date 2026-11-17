<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateApplicationRequest extends StoreApplicationRequest
{
    /** Returning the policy's response keeps its 404 for other users' applications. */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->application());
    }

    /** Only this application's own questions can be kept or reworded. */
    protected function existingQuestionRule(): Exists
    {
        return Rule::exists('screening_questions', 'public_id')->where('application_id', $this->application()?->id);
    }
}
