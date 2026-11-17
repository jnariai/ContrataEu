<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Models\CvImport;
use App\Models\MasterDocument;
use App\Models\User;

/**
 * Saves the text of one master document. Clearing an optional document (cover letter, recruiter message)
 * removes it together with its original file; the CV is required, so the FormRequest never lets it be blank.
 * Saving the CV with a PDF import's result keeps that PDF as the CV's original file (replacing an older one).
 */
class SaveMasterDocument
{
    public function handle(User $user, DocumentType $type, ?string $content, ?CvImport $import = null): ?MasterDocument
    {
        if (blank($content) && ! $type->isRequired()) {
            MasterDocument::findFor($user, $type)?->deleteWithFile();

            return null;
        }

        $document = MasterDocument::saveFor($user, $type, (string) $content);

        if ($import !== null && $type === DocumentType::Cv) {
            $this->adopt($document, $import);
        }

        return $document;
    }

    private function adopt(MasterDocument $document, CvImport $import): void
    {
        $pdf = $import->pdfFile()->first();

        if ($pdf !== null) {
            $document->removeOriginalFile();
            $pdf->moveTo($document);
        }

        $import->delete();
    }
}
