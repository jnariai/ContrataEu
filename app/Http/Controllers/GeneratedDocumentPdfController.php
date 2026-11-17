<?php

namespace App\Http\Controllers;

use App\Actions\GetDocumentPdfUrl;
use App\Models\GeneratedDocument;
use Illuminate\Http\RedirectResponse;

/** "Download PDF": a plain link that redirects to a short-lived signed URL in the private bucket. */
class GeneratedDocumentPdfController extends Controller
{
    public function show(GeneratedDocument $document, GetDocumentPdfUrl $getDocumentPdfUrl): RedirectResponse
    {
        abort_unless($document->type->hasPdf(), 404);

        return redirect()->away($getDocumentPdfUrl->handle($document));
    }
}
