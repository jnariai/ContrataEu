<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\ApplicationStatus;
use App\Enums\Language;
use App\Enums\WorkMode;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One job the user is pursuing: the job details they entered, the JD, the options and the status.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $company_name
 * @property string $role_title
 * @property string|null $job_url
 * @property string|null $location
 * @property int|null $work_mode_id
 * @property WorkMode|null $work_mode
 * @property int $language_id
 * @property Language $language
 * @property string|null $company_notes
 * @property string $job_description
 * @property bool $wants_cover_letter
 * @property bool $wants_recruiter_message
 * @property int $status_id
 * @property ApplicationStatus $status
 * @property CarbonImmutable|null $applied_at
 * @property int|null $current_generation_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read Generation|null $currentGeneration
 * @property-read Collection<int, ScreeningQuestion> $screeningQuestions
 * @property-read Collection<int, Generation> $generations
 */
#[Fillable([
    'company_name', 'role_title', 'job_url', 'location', 'work_mode', 'language', 'company_notes',
    'job_description', 'wants_cover_letter', 'wants_recruiter_message', 'status', 'applied_at',
])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'app_';

    public static function open(
        User $user,
        string $companyName,
        string $roleTitle,
        ?string $jobUrl,
        ?string $location,
        ?WorkMode $workMode,
        Language $language,
        ?string $companyNotes,
        string $jobDescription,
        bool $wantsCoverLetter,
        bool $wantsRecruiterMessage,
    ): self {
        $application = new self(['status' => ApplicationStatus::Draft]);
        $application->user()->associate($user);
        $application->revise($companyName, $roleTitle, $jobUrl, $location, $workMode, $language, $companyNotes, $jobDescription, $wantsCoverLetter, $wantsRecruiterMessage);

        return $application;
    }

    /**
     * Changing the job details or options never touches existing generations: they keep what they were made from,
     * and the next generation uses the new values (including a new output language).
     */
    public function revise(
        string $companyName,
        string $roleTitle,
        ?string $jobUrl,
        ?string $location,
        ?WorkMode $workMode,
        Language $language,
        ?string $companyNotes,
        string $jobDescription,
        bool $wantsCoverLetter,
        bool $wantsRecruiterMessage,
    ): void {
        $this->fill([
            'company_name' => $companyName,
            'role_title' => $roleTitle,
            'job_url' => $jobUrl,
            'location' => $location,
            'work_mode' => $workMode,
            'language' => $language,
            'company_notes' => $companyNotes,
            'job_description' => $jobDescription,
            'wants_cover_letter' => $wantsCoverLetter,
            'wants_recruiter_message' => $wantsRecruiterMessage,
        ]);
        $this->save();
    }

    /**
     * The user moves the application through their pipeline by hand. The first time it becomes Applied,
     * `applied_at` records when; moving away and back keeps that first date.
     */
    public function changeStatus(ApplicationStatus $status, CarbonImmutable $at): void
    {
        $this->update([
            'status' => $status,
            'applied_at' => $this->applied_at ?? ($status === ApplicationStatus::Applied ? $at : null),
        ]);
    }

    /**
     * A finished generation becomes the version the user sees. A draft becomes Ready; later statuses (Applied, …)
     * are the user's pipeline and stay as they are.
     */
    public function adoptGeneration(Generation $generation): void
    {
        $this->currentGeneration()->associate($generation);

        if ($this->status === ApplicationStatus::Draft) {
            $this->status = ApplicationStatus::Ready;
        }

        $this->save();
    }

    /** The next version number: versions keep counting up even after old ones are pruned. */
    public function nextGenerationVersion(): int
    {
        return ((int) $this->generations()->max('version')) + 1;
    }

    /** @return array<string, int> status value => number of the user's applications in it */
    public static function countsByStatusFor(User $user): array
    {
        return self::query()->whereBelongsTo($user)
            ->selectRaw('status_id, count(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id')
            ->mapWithKeys(static fn (mixed $count, int|string $statusId): array => [
                LookupCast::caseOf(ApplicationStatus::class, 'application_statuses', (int) $statusId)->value => (int) $count,
            ])
            ->all();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ScreeningQuestion, $this> */
    public function screeningQuestions(): HasMany
    {
        return $this->hasMany(ScreeningQuestion::class)->orderBy('position');
    }

    /** @return HasMany<Generation, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function currentGeneration(): BelongsTo
    {
        return $this->belongsTo(Generation::class, 'current_generation_id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function inStatus(Builder $query, ApplicationStatus ...$statuses): void
    {
        $query->whereIn('status_id', array_map(
            static fn (ApplicationStatus $status): int => LookupCast::idOf('application_statuses', $status),
            $statuses,
        ));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'work_mode' => LookupCast::of(WorkMode::class, 'work_modes', 'work_mode_id'),
            'language' => LookupCast::of(Language::class, 'languages', 'language_id'),
            'status' => LookupCast::of(ApplicationStatus::class, 'application_statuses', 'status_id'),
            'wants_cover_letter' => 'boolean',
            'wants_recruiter_message' => 'boolean',
            'applied_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
