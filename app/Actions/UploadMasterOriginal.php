<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Exceptions\MasterDocumentMissing;
use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\Models\User;
use App\ValueObjects\StoragePath;
use Illuminate\Http\UploadedFile;

/**
 * Keeps the user's original CV / cover letter file (PDF or DOCX) next to its Markdown text, for reference.
 * Uploading again replaces the previous file.
 */
class UploadMasterOriginal
{
    public function __construct(private StoreFile $storeFile) {}

    public function handle(User $user, DocumentType $type, UploadedFile $file): StoredFile
    {
        $document = MasterDocument::findFor($user, $type) ?? throw MasterDocumentMissing::forUpload($type);

        // The extension comes from the content (validated as pdf/docx), never from the user's file name.
        $path = StoragePath::forMasterOriginal($user->id, $type, (string) $file->guessExtension());

        return $this->storeFile->handle($user, $document, FilePurpose::MasterOriginal, $path, $file);
    }
}
