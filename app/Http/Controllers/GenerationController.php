<?php

namespace App\Http\Controllers;

use App\Actions\StartGeneration;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GenerationController extends Controller
{
    /** "Generate": queues a new run; the show page polls until it finishes. */
    public function store(Application $application, StartGeneration $startGeneration): RedirectResponse
    {
        $startGeneration->handle($application);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generating your documents. This usually takes a minute or two; you can follow it below.')]);

        return to_route('applications.show', $application);
    }
}
