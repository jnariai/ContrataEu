<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\CvConvention;
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
 * @property string|null $file_name the name used in PDF file names; null means first name and last surname
 * @property string|null $headline
 * @property string|null $location
 * @property string|null $work_authorization
 * @property int|null $cv_convention_id
 * @property CvConvention|null $cv_convention for English documents; null means International / EU
 * @property array{linkedin?: string|null, github?: string|null, portfolio?: string|null}|null $links
 * @property int $default_output_language_id
 * @property Language $default_output_language
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Fillable(['full_name', 'file_name', 'headline', 'location', 'work_authorization', 'cv_convention', 'links', 'default_output_language'])]
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    /** Lowercase name particles never taken as the surname for file names. */
    private const array NAME_PARTICLES = ['da', 'das', 'de', 'del', 'della', 'der', 'di', 'do', 'dos', 'du', 'e', 'la', 'le', 'van', 'von', 'y'];

    /**
     * Creates or updates the user's single profile.
     *
     * @param  array{linkedin: string|null, github: string|null, portfolio: string|null}  $links
     */
    public static function saveFor(
        User $user,
        string $fullName,
        ?string $fileName,
        ?string $headline,
        ?string $location,
        ?string $workAuthorization,
        ?CvConvention $cvConvention,
        array $links,
        Language $defaultOutputLanguage,
    ): self {
        $profile = self::query()->whereBelongsTo($user)->first() ?? new self;

        $profile->fill([
            'full_name' => $fullName,
            'file_name' => $fileName,
            'headline' => $headline,
            'location' => $location,
            'work_authorization' => $workAuthorization,
            'cv_convention' => $cvConvention,
            'links' => $links,
            'default_output_language' => $defaultOutputLanguage,
        ]);
        $profile->user()->associate($user);
        $profile->save();

        return $profile;
    }

    /** The name the candidate's PDFs are filed under: their own choice, or first name and last surname. */
    public function nameOnFiles(): string
    {
        return $this->file_name ?? self::shortName($this->full_name);
    }

    /**
     * First name and last surname, the way recruiters file and search CVs: "Jonathan Nariai dos Santos" →
     * "Jonathan Santos". Name particles (dos, da, de, van…) never stand in for the surname.
     */
    public static function shortName(string $fullName): string
    {
        $words = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $surnames = array_values(array_filter(
            array_slice($words, 1),
            static fn (string $word): bool => ! in_array(mb_strtolower($word), self::NAME_PARTICLES, true),
        ));

        return trim(($words[0] ?? '').' '.($surnames === [] ? '' : $surnames[array_key_last($surnames)]));
    }

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
            'cv_convention' => LookupCast::of(CvConvention::class, 'cv_conventions', 'cv_convention_id'),
            'default_output_language' => LookupCast::of(Language::class, 'languages', 'default_output_language_id'),
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
