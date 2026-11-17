<?php

namespace App\Actions;

use App\Models\GeneratedDocument;

/**
 * Saves the user's text for a generated document (an edit, or the original again for "revert"). A changed text makes
 * the cached PDF stale, so it is deleted first: if the save then fails, the only cost is rendering the PDF again.
 */
class UpdateGeneratedDocument
{
    public function handle(GeneratedDocument $document, string $content): GeneratedDocument
    {
        if ($content === $document->content) {
            return $document;
        }

        $document->pdfFile?->delete();
        $document->edit($content);

        return $document;
    }
}
