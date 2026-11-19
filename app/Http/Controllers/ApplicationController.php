<?php

namespace App\Http\Controllers;

use App\Actions\CreateApplication;
use App\Actions\DeleteApplication;
use App\Actions\UpdateApplication;
use App\Enums\ApplicationStatus;
use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Enums\WorkMode;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Http\Requests\UpdateGeneratedAnswerRequest;
use App\Http\Requests\UpdateGeneratedDocumentRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\GenerationResource;
use App\Models\AiCredential;
use App\Models\Application;
use App\Models\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('applications/create', [
            'defaultLanguage' => ($request->user()->candidateProfile->default_output_language ?? Language::En)->value,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreApplicationRequest $request, CreateApplication $createApplication): RedirectResponse
    {
        $application = $createApplication->handle($request->user(), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application created.')]);

        return to_route('applications.show', $application);
    }

    /**
     * Shows one version: `?version=N` when it exists, else the current one, else the newest. Only successful runs
     * are versions; the run in progress (for its steps) and the last failures (the failure log) come separately.
     */
    public function show(Request $request, Application $application): Response
    {
        $runs = $application->generations()->orderByDesc('id')->get();
        $versions = $runs->filter(fn (Generation $generation): bool => $generation->status === GenerationStatus::Succeeded)
            ->sortByDesc('version')->values();
        $running = $runs->first(fn (Generation $generation): bool => ! $generation->isFinished());
        $failures = $runs->filter(fn (Generation $generation): bool => $generation->status === GenerationStatus::Failed)->values();
        $newestFinished = $runs->first(fn (Generation $generation): bool => $generation->isFinished());

        $selected = $versions->firstWhere('version', $request->integer('version'))
            ?? $versions->firstWhere('id', $application->current_generation_id)
            ?? $versions->first();
        $selected?->load(['documents', 'answers.screeningQuestion']);

        return Inertia::render('applications/show', [
            'application' => ApplicationResource::make($application->load('screeningQuestions'))->resolve(),
            'generation' => $selected === null ? null : GenerationResource::make($selected)->resolve(),
            'versions' => $versions->map(fn (Generation $generation): array => [
                'version' => $generation->version,
                'language' => ['value' => $generation->language->value, 'label' => $generation->language->label()],
                'finished_at' => $generation->finished_at?->toIso8601ZuluString(),
                'current' => $generation->id === $application->current_generation_id,
            ])->values(),
            'run' => $running === null ? null : [
                'status' => ['value' => $running->status->value, 'label' => $running->status->label()],
                'provider' => $running->provider->label(),
                'model' => $running->model,
                'queued_at' => $running->created_at->toIso8601ZuluString(),
                'started_at' => $running->started_at?->toIso8601ZuluString(),
            ],
            'failures' => $failures->map(fn (Generation $generation): array => [
                'id' => $generation->public_id,
                'provider' => $generation->provider->label(),
                'model' => $generation->model,
                'language' => ['value' => $generation->language->value, 'label' => $generation->language->label()],
                'error' => $generation->error,
                // Generating again with the same key would fail the same way: the page sends the user to their keys.
                'fix_in_ai_keys' => $generation->failureIsFixedInAiKeys(),
                'queued_at' => $generation->created_at->toIso8601ZuluString(),
                'started_at' => $generation->started_at?->toIso8601ZuluString(),
                'finished_at' => $generation->finished_at?->toIso8601ZuluString(),
            ])->values(),
            // The newest finished run failed: say so above the documents (which are still the last good version).
            'lastAttemptFailed' => $newestFinished?->status === GenerationStatus::Failed,
            // The user's keys, for the "Using" switcher next to Generate; never the key itself.
            'aiKeys' => $request->user()->aiCredentials()->orderByDesc('is_default')->latest('id')->get()
                ->map(fn (AiCredential $credential): array => [
                    'id' => $credential->public_id,
                    'name' => $credential->displayName(),
                    'provider' => $credential->provider->label(),
                    'model' => $credential->model,
                    'active' => $credential->is_default,
                ])->values(),
            'statuses' => ApplicationStatus::options(),
            'limits' => [
                'document' => UpdateGeneratedDocumentRequest::MAX_CHARACTERS,
                'answer' => UpdateGeneratedAnswerRequest::MAX_CHARACTERS,
            ],
            'can' => [
                'update' => $request->user()->can('update', $application),
                'delete' => $request->user()->can('delete', $application),
                // Permission plus state: nothing may be queued or running for this application.
                'generate' => $request->user()->can('generate', $application) && $running === null,
            ],
        ]);
    }

    public function edit(Application $application): Response
    {
        return Inertia::render('applications/edit', [
            'application' => ApplicationResource::make($application->load('screeningQuestions'))->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application, UpdateApplication $updateApplication): RedirectResponse
    {
        $updateApplication->handle($application, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application updated.')]);

        return to_route('applications.show', $application);
    }

    public function destroy(Application $application, DeleteApplication $deleteApplication): RedirectResponse
    {
        $deleteApplication->handle($application);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application deleted.')]);

        return to_route('dashboard');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'workModes' => WorkMode::options(),
            'limits' => [
                'jobDescription' => StoreApplicationRequest::MAX_JOB_DESCRIPTION,
                'questions' => StoreApplicationRequest::MAX_QUESTIONS,
            ],
        ];
    }
}
