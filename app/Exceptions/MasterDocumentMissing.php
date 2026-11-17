<?php

namespace App\Exceptions;

use App\Enums\DocumentType;

class MasterDocumentMissing extends BusinessRuleException
{
    public static function forUpload(DocumentType $type): self
    {
        return new self(
            message: __('Save the text of your :document first, then attach the original file.', ['document' => mb_strtolower($type->label())]),
            errorCode: 'master_document.missing_for_upload',
            logContext: ['type' => $type->value],
        );
    }
}
