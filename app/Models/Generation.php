<?php

namespace App\Models;

use App\Casts\GenerationReportCast;
use App\Casts\LookupCast;
use App\Datas\GenerationReport;
use App\Enums\AiProvider;
use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AI run for an application. Provider, model and language are snapshots taken when it started.
 *
 * @property int $id
 * @property string $public_id
 * @property int $application_id
 * @property int|null $version set only when the run succeeds; failed and unfinished runs have none
 * @property int $status_id
 * @property GenerationStatus $status
 * @property int $provider_id
 * @property AiProvider $provider
 * @property string $model
 * @property int $language_id
 * @property Language $language
 * @property GenerationReport|null $report
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $error
 * @property string|null $error_code
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Application $application
 * @property-read Collection<int, GeneratedDocument> $documents
 * @property-read Collection<int, GeneratedAnswer> $answers
 */
#[Fillable([
    'version', 'status', 'provider', 'model', 'language', 'report',
    'input_tokens', 'output_tokens', 'error', 'error_code', 'started_at', 'finished_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'gen_';

    /**
     * A new run waiting for the queue, without a version number: it gets one only if it succeeds. Provider, model
     * and language are copied now, so later changes to the key or the application never rewrite what it used.
     */
    public static function queue(Application $application, AiProvider $provider, string $model, Language $language): self
    {
        $generation = new self([
            'status' => GenerationStatus::Queued,
            'provider' => $provider,
            'model' => $model,
            'language' => $language,
        ]);
        $generation->application()->associate($application);
        $generation->save();

        return $generation;
    }

    /**
     * Moves Queued → Running. Returns false when it isn't queued any more (a retried or duplicate job), so the caller
     * does nothing instead of running the AI twice.
     */
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

        $this->refresh();

        return $claimed;
    }

    /** Only a successful run becomes a numbered version; the caller picks the number under a lock. */
    public function succeed(int $version, GenerationReport $report, int $inputTokens, int $outputTokens, CarbonImmutable $at): void
    {
        $this->update([
            'version' => $version,
            'status' => GenerationStatus::Succeeded,
            'report' => $report,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'error' => null,
            'error_code' => null,
            'finished_at' => $at,
        ]);
    }

    /**
     * `$error` is shown to the user: it must already be safe (no keys, no raw provider payloads). `$errorCode` is the
     * business exception's stable code when the cause is known, so the page can point to the fix.
     */
    public function fail(string $error, CarbonImmutable $at, ?string $errorCode = null): void
    {
        if ($this->status->isFinished()) {
            return;
        }

        $this->update([
            'status' => GenerationStatus::Failed,
            'error' => $error,
            'error_code' => $errorCode,
            'finished_at' => $at,
        ]);
    }

    /**
     * The run failed for a reason the user fixes on their AI keys (a rejected or deleted key, no credit, a model the
     * provider doesn't know, a private address), so generating again with the same key would fail the same way.
     */
    public function failureIsFixedInAiKeys(): bool
    {
        return in_array($this->error_code, [
            'ai_provider.invalid_key',
            'ai_provider.out_of_credit',
            'ai_provider.invalid_request',
            'ai_provider.base_url_not_public',
            'generation.key_removed',
        ], true);
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function inStatus(Builder $query, GenerationStatus ...$statuses): void
    {
        $query->whereIn('status_id', array_map(
            static fn (GenerationStatus $status): int => LookupCast::idOf('generation_statuses', $status),
            $statuses,
        ));
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

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return HasMany<GeneratedDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    /** @return HasMany<GeneratedAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(GeneratedAnswer::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => LookupCast::of(GenerationStatus::class, 'generation_statuses', 'status_id'),
            'provider' => LookupCast::of(AiProvider::class, 'ai_providers', 'provider_id'),
            'language' => LookupCast::of(Language::class, 'languages', 'language_id'),
            'report' => GenerationReportCast::class,
            'version' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
