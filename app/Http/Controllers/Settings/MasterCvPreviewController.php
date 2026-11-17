<?php

namespace App\Http\Controllers\Settings;

use App\Actions\RenderDocumentPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewDocumentRequest;
use Illuminate\Http\JsonResponse;

/** "Preview" on the master CV: the editor's text in the CV's PDF template, as HTML for a sandboxed iframe. */
class MasterCvPreviewController extends Controller
{
    public function store(PreviewDocumentRequest $request, RenderDocumentPdf $renderDocumentPdf): JsonResponse
    {
        $user = $request->user();
        $profile = $user->candidateProfile;

        return response()->json([
            'html' => $renderDocumentPdf->cvHtml(
                $request->string('content')->toString(),
                $profile->default_output_language ?? $user->locale,
                $profile->full_name ?? $user->name,
                forScreen: true,
            ),
        ]);
    }
}
