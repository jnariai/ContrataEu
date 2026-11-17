<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\KeepsOriginalContent;
use Carbon\CarbonImmutable;
use Database\Factories\GeneratedDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

/**
 * Tailored text for one document type inside a generation. Editable; `original_content` allows a revert.
 *
 * @property int $id
 * @property string $public_id
 * @property int $generation_id
 * @property int $type_id
 * @property DocumentType $type
 * @property string $content
 * @property string $original_content
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Generation $generation
 * @property-read StoredFile|null $pdfFile
 */
#[Fillable(['type', 'content', 'original_content', 'edited_at'])]
class GeneratedDocument extends Model
{
    /** @use HasFactory<GeneratedDocumentFactory> */
    use HasFactory;

    use HasPublicId;
    use KeepsOriginalContent;

    public const string PUBLIC_ID_PREFIX = 'doc_';

    /** The AI's text becomes both the editable content and the original kept for "revert". */
    public static function write(Generation $generation, DocumentType $type, string $content): self
    {
        $document = new self(['type' => $type, 'content' => $content, 'original_content' => $content]);
        $document->generation()->associate($generation);
        $document->save();

        return $document;
    }

    /**
     * `Firstname-Lastname-Role.pdf`, plus `-Cover-Letter` (or the pt-BR suffix) for a cover letter. ASCII only, so
     * every ATS and mail client keeps it intact. Callers eager-load `generation.application`.
     */
    public function pdfFilename(string $candidateName): string
    {
        $parts = [$candidateName, $this->generation->application->role_title];

        if ($this->type === DocumentType::CoverLetter) {
            $parts[] = $this->generation->language->coverLetterFilenameSuffix();
        }

        $slug = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii(implode(' ', $parts))), '-');

        return ($slug === '' ? $this->type->value : $slug).'.pdf';
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /** @return MorphOne<StoredFile, $this> */
    public function pdfFile(): MorphOne
    {
        return $this->morphOne(StoredFile::class, 'fileable')
            ->where('purpose_id', LookupCast::idOf('file_purposes', FilePurpose::DocumentPdf));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => LookupCast::of(DocumentType::class, 'document_types', 'type_id'),
            'edited_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
