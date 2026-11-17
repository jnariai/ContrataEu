<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\MasterDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = ApplicationStatus::tryFrom((string) $request->query('status'));

        $setup = [
            'profile' => $user->candidateProfile()->exists(),
            'masterCv' => MasterDocument::findFor($user, DocumentType::Cv) !== null,
            'aiKey' => $user->aiCredentials()->where('is_default', true)->exists(),
        ];

        return Inertia::render('dashboard', [
            'setup' => [...$setup, 'complete' => ! in_array(false, $setup, true)],
            'filters' => ['status' => $status?->value],
            'statuses' => ApplicationStatus::options(),
            'counts' => Application::countsByStatusFor($user),
            'applications' => $user->applications()
                ->when($status, fn ($query, ApplicationStatus $status) => $query->inStatus($status))
                ->latest('updated_at')
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Application $application): array => [
                    'id' => $application->public_id,
                    'company_name' => $application->company_name,
                    'role_title' => $application->role_title,
                    'location' => $application->location,
                    'work_mode' => $application->work_mode?->label(),
                    'language' => $application->language->value,
                    'status' => ['value' => $application->status->value, 'label' => $application->status->label(), 'color' => $application->status->color()],
                    'applied_at' => $application->applied_at?->toIso8601ZuluString(),
                    'updated_at' => $application->updated_at->toIso8601ZuluString(),
                ]),
        ]);
    }
}
