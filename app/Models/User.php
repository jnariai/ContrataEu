<?php

namespace App\Models;

use App\Casts\LookupCast;
use App\Enums\Language;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property int $locale_id
 * @property Language $locale
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CandidateProfile|null $candidateProfile
 * @property-read Collection<int, MasterDocument> $masterDocuments
 * @property-read Collection<int, PromptOverride> $promptOverrides
 * @property-read Collection<int, AiCredential> $aiCredentials
 * @property-read Collection<int, Application> $applications
 * @property-read Collection<int, StoredFile> $storedFiles
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /** @return HasOne<CandidateProfile, $this> */
    public function candidateProfile(): HasOne
    {
        return $this->hasOne(CandidateProfile::class);
    }

    /** @return HasMany<MasterDocument, $this> */
    public function masterDocuments(): HasMany
    {
        return $this->hasMany(MasterDocument::class);
    }

    /** @return HasMany<PromptOverride, $this> */
    public function promptOverrides(): HasMany
    {
        return $this->hasMany(PromptOverride::class);
    }

    /** @return HasMany<AiCredential, $this> */
    public function aiCredentials(): HasMany
    {
        return $this->hasMany(AiCredential::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<StoredFile, $this> */
    public function storedFiles(): HasMany
    {
        return $this->hasMany(StoredFile::class);
    }

    /** Mail and notifications (verification, password reset) go out in the user's UI language. */
    public function preferredLocale(): string
    {
        return $this->locale->laravelLocale();
    }

    public function switchLocale(Language $locale): void
    {
        $this->update(['locale' => $locale]);
    }

    protected function casts(): array
    {
        return [
            'locale' => LookupCast::of(Language::class, 'languages', 'locale_id'),
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
