<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends FormRequest
{
    /** Returning the policy's response keeps its 404 for other users' applications. */
    public function authorize(): Response
    {
        $application = $this->route('application');

        return $application instanceof Application ? Gate::inspect('update', $application) : Response::denyAsNotFound();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ApplicationStatus::class)]];
    }

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::from($this->string('status')->toString());
    }
}
