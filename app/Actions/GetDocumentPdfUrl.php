<?php

namespace App\Actions;

use App\Enums\FilePurpose;
use App\Models\GeneratedDocument;
use App\ValueObjects\StoragePath;
use InvalidArgumentException;
use LogicException;

/**
 * A short-lived download URL for a document's PDF. The PDF is rendered once and kept in the bucket; editing or
 * reverting the document deletes it (UpdateGeneratedDocument), so the next download renders the new text.
 */
class GetDocumentPdfUrl
{
    public function __construct(
        private RenderDocumentPdf $renderDocumentPdf,
        private StoreFile $storeFile,
        private GetFileUrl $getFileUrl,
    ) {}

    public function handle(GeneratedDocument $document): string
    {
        if (! $document->type->hasPdf()) {
            throw new InvalidArgumentException("[{$document->type->value}] documents have no PDF.");
        }

        $document->loadMissing(['pdfFile', 'generation.application.user.candidateProfile']);
        $application = $document->generation->application;
        $filename = $document->pdfFilename($application->user->nameOnFiles());

        $file = $document->pdfFile ?? $this->storeFile->handle(
            $application->user,
            $document,
            FilePurpose::DocumentPdf,
            StoragePath::forDocumentPdf($application->user_id, $application->id, $document->generation->version ?? throw new LogicException('Only a successful generation has documents.'), $document->type),
            $this->renderDocumentPdf->handle($document),
            $filename,
        );

        return $this->getFileUrl->handle($file, $filename);
    }
}
