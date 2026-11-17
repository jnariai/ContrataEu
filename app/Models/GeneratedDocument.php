<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\GeneratedDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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

    public const string PUBLIC_ID_PREFIX = 'doc_';

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
