<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\Language;
use Carbon\CarbonImmutable;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Facts about the candidate reused in every prompt.
 *
 * @property int $id
 * @property int $user_id
 * @property string $full_name
 * @property string|null $headline
 * @property string|null $location
 * @property string|null $work_authorization
 * @property string|null $region_convention
 * @property array{linkedin?: string|null, github?: string|null, portfolio?: string|null}|null $links
 * @property int $default_output_language_id
 * @property Language $default_output_language
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Fillable(['full_name', 'headline', 'location', 'work_authorization', 'region_convention', 'links', 'default_output_language'])]
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'default_output_language' => LookupCast::of(Language::class, 'languages', 'default_output_language_id'),
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
