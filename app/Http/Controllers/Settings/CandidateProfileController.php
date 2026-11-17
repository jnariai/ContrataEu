<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CvConvention;
use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCandidateProfileRequest;
use App\Models\CandidateProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CandidateProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $profile = $request->user()->candidateProfile;

        return Inertia::render('settings/candidate', [
            'profile' => [
                'full_name' => $profile->full_name ?? $request->user()->name,
                'headline' => $profile?->headline,
                'location' => $profile?->location,
                'work_authorization' => $profile?->work_authorization,
                'cv_convention' => ($profile->cv_convention ?? CvConvention::International)->value,
                'links' => [
                    'linkedin' => $profile?->links['linkedin'] ?? null,
                    'github' => $profile?->links['github'] ?? null,
                    'portfolio' => $profile?->links['portfolio'] ?? null,
                ],
                'default_output_language' => ($profile->default_output_language ?? $request->user()->locale)->value,
            ],
            'exists' => $profile !== null,
            'cvConventions' => CvConvention::options(),
            'brazilConvention' => ['label' => CvConvention::Brazil->label(), 'description' => CvConvention::Brazil->description()],
        ]);
    }

    public function update(UpdateCandidateProfileRequest $request): RedirectResponse
    {
        CandidateProfile::saveFor(
            $request->user(),
            fullName: $request->string('full_name')->trim()->toString(),
            headline: $request->optional('headline'),
            location: $request->optional('location'),
            workAuthorization: $request->optional('work_authorization'),
            cvConvention: $request->enum('cv_convention', CvConvention::class),
            links: $request->links(),
            defaultOutputLanguage: Language::from($request->string('default_output_language')->toString()),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Candidate profile saved.')]);

        return to_route('candidate-profile.edit');
    }
}
