<?php

namespace App\Http\Controllers;

use App\Actions\RenderDocumentPdf;
use App\Http\Requests\PreviewDocumentRequest;
use App\Models\GeneratedDocument;
use Illuminate\Http\JsonResponse;

/** "Preview": the document's current editor text in its PDF template, as HTML for a sandboxed iframe. */
class GeneratedDocumentPreviewController extends Controller
{
    public function store(PreviewDocumentRequest $request, GeneratedDocument $document, RenderDocumentPdf $renderDocumentPdf): JsonResponse
    {
        abort_unless($document->type->hasPdf(), 404);

        return response()->json([
            'html' => $renderDocumentPdf->html($document, $request->string('content')->toString(), forScreen: true),
        ]);
    }
}
