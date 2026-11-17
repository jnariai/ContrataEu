<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGeneratedAnswerRequest;
use App\Models\GeneratedAnswer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GeneratedAnswerController extends Controller
{
    public function update(UpdateGeneratedAnswerRequest $request, GeneratedAnswer $answer): RedirectResponse
    {
        $answer->edit($request->string('content')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Answer saved.')]);

        return back();
    }
}
