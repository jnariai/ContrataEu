<?php

namespace App\Http\Controllers\Settings;

use App\Actions\StartCvImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCvImportRequest;
use App\Models\CvImport;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** "Import from PDF" on the CV: upload (queues the AI read) and discard a result. */
class CvImportController extends Controller
{
    public function store(StoreCvImportRequest $request, StartCvImport $startCvImport): RedirectResponse
    {
        $startCvImport->handle($request->user(), $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reading your CV. This usually takes under a minute.')]);

        return to_route('master-documents.index');
    }

    public function destroy(CvImport $cvImport): RedirectResponse
    {
        $cvImport->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import discarded. Your saved CV is unchanged.')]);

        return to_route('master-documents.index');
    }
}
