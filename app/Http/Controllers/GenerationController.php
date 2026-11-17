<?php

namespace App\Http\Controllers;

use App\Actions\StartGeneration;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GenerationController extends Controller
{
    /** "Generate": queues a new version; the show page polls until it finishes. */
    public function store(Application $application, StartGeneration $startGeneration): RedirectResponse
    {
        $generation = $startGeneration->handle($application);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generating version :version. This usually takes under a minute.', ['version' => $generation->version])]);

        return to_route('applications.show', $application);
    }
}
