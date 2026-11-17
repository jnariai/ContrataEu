<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\AiProvider;
use App\Enums\FilePurpose;
use App\Enums\GenerationStatus;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\CvImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A CV PDF being read into Markdown by the user's AI key. Short-lived: saving the master CV with the result adopts
 * the PDF as the CV's original file and deletes the import; discarded and stale imports go with their PDF.
 * Statuses reuse GenerationStatus (queued → running → succeeded | failed), like any AI run.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $status_id
 * @property GenerationStatus $status
 * @property int $provider_id
 * @property AiProvider $provider
 * @property string $model
 * @property string|null $markdown
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read StoredFile|null $pdfFile
 */
#[Fillable(['status', 'provider', 'model', 'markdown', 'error', 'started_at', 'finished_at'])]
class CvImport extends Model
{
    /** @use HasFactory<CvImportFactory> */
    use HasFactory;

    use HasPublicId;
    use Prunable;

    public const string PUBLIC_ID_PREFIX = 'imp_';

    /** Imports nobody saved or discarded are deleted (with their PDF) after this many hours. */
    public const int KEEP_HOURS = 24;

    /** Provider and model are copied from the key used, so the run never depends on later key changes. */
    public static function queue(User $user, AiProvider $provider, string $model): self
    {
        $import = new self(['status' => GenerationStatus::Queued, 'provider' => $provider, 'model' => $model]);
        $import->user()->associate($user);
        $import->save();

        return $import;
    }

    /** Atomically claims a queued import; false when another worker already did (or it was discarded). */
    public function start(CarbonImmutable $at): bool
    {
        $claimed = self::query()
            ->whereKey($this->id)
            ->where('status_id', LookupCast::idOf('generation_statuses', GenerationStatus::Queued))
            ->update([
                'status_id' => LookupCast::idOf('generation_statuses', GenerationStatus::Running),
                'started_at' => $at,
                'updated_at' => $at,
            ]) === 1;

        if ($claimed) {
            $this->refresh();
        }

        return $claimed;
    }

    public function succeed(string $markdown, CarbonImmutable $at): void
    {
        $this->update(['status' => GenerationStatus::Succeeded, 'markdown' => $markdown, 'error' => null, 'finished_at' => $at]);
    }

    /** `$error` is shown to the user: it must already be safe (no keys, no raw provider payloads). */
    public function fail(string $error, CarbonImmutable $at): void
    {
        if ($this->status->isFinished()) {
            return;
        }

        $this->update(['status' => GenerationStatus::Failed, 'error' => $error, 'finished_at' => $at]);
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /** A finished import whose Markdown can fill the CV editor. */
    public function isReady(): bool
    {
        return $this->status === GenerationStatus::Succeeded && $this->markdown !== null;
    }

    /** The newest import of the user still worth showing on the documents page. */
    public static function latestFor(User $user): ?self
    {
        return self::query()->whereBelongsTo($user)->latest('id')->with('pdfFile')->first();
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subHours(self::KEEP_HOURS));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphOne<StoredFile, $this> */
    public function pdfFile(): MorphOne
    {
        return $this->morphOne(StoredFile::class, 'fileable')
            ->where('purpose_id', LookupCast::idOf('file_purposes', FilePurpose::MasterOriginal));
    }

    /** SQL cascades skip model events, so the PDF (row and object) is deleted here, unless it was adopted already. */
    protected static function booted(): void
    {
        static::deleting(static function (self $import): void {
            $import->pdfFile()->first()?->delete();
        });
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function unfinished(Builder $query): void
    {
        $query->whereIn('status_id', array_map(
            static fn (GenerationStatus $status): int => LookupCast::idOf('generation_statuses', $status),
            GenerationStatus::unfinished(),
        ));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => LookupCast::of(GenerationStatus::class, 'generation_statuses', 'status_id'),
            'provider' => LookupCast::of(AiProvider::class, 'ai_providers', 'provider_id'),
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
