<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\FilePurpose;
use App\Models\Concerns\HasPublicId;
use App\ValueObjects\StoragePath;
use Carbon\CarbonImmutable;
use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * One object in the private R2 bucket. Deleting the row deletes the object (see the `deleting` hook);
 * SQL cascades don't fire model events, so parents delete their files explicitly.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $fileable_type
 * @property int $fileable_id
 * @property int $purpose_id
 * @property FilePurpose $purpose
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read MasterDocument|GeneratedDocument $fileable
 */
#[Fillable(['purpose', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'])]
class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'file_';

    /** Records an object that was just written to the bucket. */
    public static function record(
        User $owner,
        MasterDocument|GeneratedDocument $fileable,
        FilePurpose $purpose,
        string $disk,
        StoragePath $path,
        string $mimeType,
        int $sizeBytes,
        ?string $originalName,
    ): self {
        $file = new self([
            'purpose' => $purpose,
            'disk' => $disk,
            'path' => $path->value,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'original_name' => $originalName,
        ]);
        $file->user()->associate($owner);
        $file->fileable()->associate($fileable);
        $file->save();

        return $file;
    }

    /** The object at the same path was overwritten: keep the row, refresh what describes it. */
    public function refreshObject(string $mimeType, int $sizeBytes, ?string $originalName): void
    {
        $this->update(['mime_type' => $mimeType, 'size_bytes' => $sizeBytes, 'original_name' => $originalName]);
    }

    /**
     * Deleting the row deletes the object first, so a failed delete keeps the row (and the object stays tracked).
     * SQL cascades skip this hook, which is why parents delete their files explicitly.
     */
    protected static function booted(): void
    {
        static::deleting(static function (self $file): void {
            Storage::disk($file->disk)->delete($file->path);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'purpose' => LookupCast::of(FilePurpose::class, 'file_purposes', 'purpose_id'),
            'size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
