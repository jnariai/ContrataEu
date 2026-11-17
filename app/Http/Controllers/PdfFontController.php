<?php

namespace App\Http\Controllers;

use App\Actions\RenderDocumentPdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the Inter fonts (OFL) to on-screen document previews. The preview is a sandboxed iframe with an opaque
 * origin, so the fonts are public and sent with CORS; only the files the PDF template uses can be requested.
 */
class PdfFontController extends Controller
{
    public function show(string $font): BinaryFileResponse
    {
        abort_unless(array_key_exists($font, RenderDocumentPdf::FONTS), 404);

        return response()->file(resource_path('fonts/'.$font), [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
