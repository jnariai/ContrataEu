<?php

namespace App\Exceptions;

use App\Enums\AiProvider;

/** The import was queued, but something it needs disappeared or the answer was unusable. */
class CvImportCannotRun extends BusinessRuleException
{
    public static function keyRemoved(AiProvider $provider): self
    {
        return new self(
            message: __('Your :provider key was removed before the PDF could be read. Add it again, then upload the PDF again.', ['provider' => $provider->label()]),
            errorCode: 'cv_import.key_removed',
        );
    }

    public static function fileMissing(): self
    {
        return new self(
            message: __('The uploaded PDF is gone. Upload it again.'),
            errorCode: 'cv_import.file_missing',
        );
    }

    public static function noCvFound(): self
    {
        return new self(
            message: __('No CV text could be read from this PDF. Check that it is your CV (not a scan of a photo), or paste the text instead.'),
            errorCode: 'cv_import.no_cv_found',
        );
    }
}
