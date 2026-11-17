<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ScreeningQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $application_id
 * @property string $question
 * @property int $position
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Application $application
 */
#[Fillable(['question', 'position'])]
class ScreeningQuestion extends Model
{
    /** @use HasFactory<ScreeningQuestionFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'q_';

    public static function ask(Application $application, string $question, int $position): self
    {
        $screeningQuestion = new self(['question' => $question, 'position' => $position]);
        $screeningQuestion->application()->associate($application);
        $screeningQuestion->save();

        return $screeningQuestion;
    }

    /** Rewording keeps the row, so answers in earlier generations stay linked to it. */
    public function rephrase(string $question, int $position): void
    {
        $this->update(['question' => $question, 'position' => $position]);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
