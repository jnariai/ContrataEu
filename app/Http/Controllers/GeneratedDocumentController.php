<?php

namespace App\Http\Controllers;

use App\Actions\UpdateGeneratedDocument;
use App\Http\Requests\UpdateGeneratedDocumentRequest;
use App\Models\GeneratedDocument;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GeneratedDocumentController extends Controller
{
    public function update(UpdateGeneratedDocumentRequest $request, GeneratedDocument $document, UpdateGeneratedDocument $updateGeneratedDocument): RedirectResponse
    {
        $updateGeneratedDocument->handle($document, $request->string('content')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':document saved.', ['document' => $document->type->label()])]);

        return back();
    }
}
