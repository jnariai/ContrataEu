<?php

namespace App\Http\Controllers;

use App\Models\GeneratedAnswer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** "Revert": brings back the answer the AI wrote for this version. */
class GeneratedAnswerRevertController extends Controller
{
    public function store(GeneratedAnswer $answer): RedirectResponse
    {
        $answer->revert();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Answer reverted to the generated text.')]);

        return back();
    }
}
