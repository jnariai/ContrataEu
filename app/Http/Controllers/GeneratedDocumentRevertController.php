<?php

namespace App\Http\Controllers;

use App\Actions\UpdateGeneratedDocument;
use App\Models\GeneratedDocument;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** "Revert": brings back the text the AI wrote for this version. */
class GeneratedDocumentRevertController extends Controller
{
    public function store(GeneratedDocument $document, UpdateGeneratedDocument $updateGeneratedDocument): RedirectResponse
    {
        $updateGeneratedDocument->handle($document, $document->original_content);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':document reverted to the generated text.', ['document' => $document->type->label()])]);

        return back();
    }
}
