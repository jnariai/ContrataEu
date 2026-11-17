<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Models\MasterDocument;
use App\Models\User;

/**
 * Saves the text of one master document. Clearing an optional document (cover letter, recruiter message)
 * removes it together with its original file; the CV is required, so the FormRequest never lets it be blank.
 */
class SaveMasterDocument
{
    public function handle(User $user, DocumentType $type, ?string $content): ?MasterDocument
    {
        if (blank($content) && ! $type->isRequired()) {
            MasterDocument::findFor($user, $type)?->deleteWithFile();

            return null;
        }

        return MasterDocument::saveFor($user, $type, (string) $content);
    }
}
