<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\GeneratedAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The answer to one screening question inside a generation. Editable; `original_content` allows a revert.
 *
 * @property int $id
 * @property string $public_id
 * @property int $generation_id
 * @property int $screening_question_id
 * @property string $content
 * @property string $original_content
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Generation $generation
 * @property-read ScreeningQuestion $screeningQuestion
 */
#[Fillable(['content', 'original_content', 'edited_at'])]
class GeneratedAnswer extends Model
{
    /** @use HasFactory<GeneratedAnswerFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'ans_';

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /** @return BelongsTo<ScreeningQuestion, $this> */
    public function screeningQuestion(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestion::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'edited_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
