<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ApplicationStatusController extends Controller
{
    public function update(UpdateApplicationStatusRequest $request, Application $application): RedirectResponse
    {
        $application->changeStatus($request->status(), now()->toImmutable());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status changed to :status.', ['status' => $request->status()->label()])]);

        return back();
    }
}
