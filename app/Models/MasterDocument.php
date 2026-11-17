<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use Carbon\CarbonImmutable;
use Database\Factories\MasterDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * The user's base document for one type (Markdown). The CV is required; the others are inspiration.
 *
 * @property int $id
 * @property int $user_id
 * @property int $type_id
 * @property DocumentType $type
 * @property string $content
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read StoredFile|null $originalFile
 */
#[Fillable(['type', 'content'])]
class MasterDocument extends Model
{
    /** @use HasFactory<MasterDocumentFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphOne<StoredFile, $this> */
    public function originalFile(): MorphOne
    {
        return $this->morphOne(StoredFile::class, 'fileable')
            ->where('purpose_id', LookupCast::idOf('file_purposes', FilePurpose::MasterOriginal));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => LookupCast::of(DocumentType::class, 'document_types', 'type_id'),
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
