<?php

namespace App\Http\Controllers;

use App\Actions\CreateApplication;
use App\Actions\DeleteApplication;
use App\Actions\UpdateApplication;
use App\Enums\ApplicationStatus;
use App\Enums\Language;
use App\Enums\WorkMode;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Http\Requests\UpdateGeneratedAnswerRequest;
use App\Http\Requests\UpdateGeneratedDocumentRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\GenerationResource;
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
     * Shows one version: `?version=N` when it exists, else the current one (the last that succeeded), else the latest.
     * `versions` lists every kept version, so the page can show progress or a failure of a newer run.
     */
    public function show(Request $request, Application $application): Response
    {
        $versions = $application->generations()->orderByDesc('version')->get();
        $latest = $versions->first();

        $selected = $versions->firstWhere('version', $request->integer('version'))
            ?? $versions->firstWhere('id', $application->current_generation_id)
            ?? $latest;
        $selected?->load(['documents', 'answers.screeningQuestion']);

        return Inertia::render('applications/show', [
            'application' => ApplicationResource::make($application->load('screeningQuestions'))->resolve(),
            'generation' => $selected === null ? null : GenerationResource::make($selected)->resolve(),
            'versions' => $versions->map(fn (Generation $generation): array => [
                'version' => $generation->version,
                'status' => ['value' => $generation->status->value, 'label' => $generation->status->label(), 'finished' => $generation->isFinished()],
                'language' => ['value' => $generation->language->value, 'label' => $generation->language->label()],
                'error' => $generation->error,
                'finished_at' => $generation->finished_at?->toIso8601ZuluString(),
                'current' => $generation->id === $application->current_generation_id,
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
                'generate' => $request->user()->can('generate', $application) && ($latest === null || $latest->isFinished()),
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
