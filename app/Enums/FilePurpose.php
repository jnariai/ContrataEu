<?php

namespace App\Enums;

enum FilePurpose: string
{
    case MasterOriginal = 'master_original';
    case DocumentPdf = 'document_pdf';

    /** @return list<string> file extensions accepted for this purpose (validated by content with the `mimes` rule) */
    public function allowedMimes(): array
    {
        return match ($this) {
            self::MasterOriginal => ['pdf', 'docx'],
            self::DocumentPdf => ['pdf'],
        };
    }

    public function maxKilobytes(): int
    {
        return match ($this) {
            self::MasterOriginal => 5 * 1024,
            self::DocumentPdf => 10 * 1024,
        };
    }
}
