<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\AiProvider;
use App\Enums\GenerationStatus;
use App\Enums\Language;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property int $version
 * @property int $status_id
 * @property GenerationStatus $status
 * @property int $provider_id
 * @property AiProvider $provider
 * @property string $model
 * @property int $language_id
 * @property Language $language
 * @property array<string, mixed>|null $report
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $error
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
    'input_tokens', 'output_tokens', 'error', 'started_at', 'finished_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'gen_';

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
            'report' => 'array',
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
