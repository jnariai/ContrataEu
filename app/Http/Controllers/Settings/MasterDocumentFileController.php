<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UploadMasterOriginal;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreMasterDocumentFileRequest;
use App\Models\MasterDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterDocumentFileController extends Controller
{
    public function store(StoreMasterDocumentFileRequest $request, DocumentType $type, UploadMasterOriginal $uploadMasterOriginal): RedirectResponse
    {
        $uploadMasterOriginal->handle($request->user(), $type, $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Original file attached.')]);

        return to_route('master-documents.index');
    }

    public function destroy(Request $request, DocumentType $type): RedirectResponse
    {
        MasterDocument::findFor($request->user(), $type)?->removeOriginalFile();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Original file removed.')]);

        return to_route('master-documents.index');
    }
}
