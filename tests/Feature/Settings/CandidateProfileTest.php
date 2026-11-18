<?php

use App\Enums\CvConvention;
use App\Enums\Language;
use App\Models\CandidateProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function validCandidateProfile(array $overrides = []): array
{
    return [
        'full_name' => 'Ana Souza',
        'headline' => 'Senior Backend Engineer',
        'location' => 'Lisbon, Portugal',
        'work_authorization' => 'EU citizen',
        'cv_convention' => 'us',
        'links' => ['linkedin' => 'https://linkedin.com/in/ana', 'github' => 'https://github.com/ana', 'portfolio' => ''],
        'default_output_language' => 'pt-BR',
        ...$overrides,
    ];
}

it('shows an empty profile prefilled with the account name and UI language', function (): void {
    $user = User::factory()->create(['name' => 'Ana Souza', 'locale' => Language::PtBr]);

    $this->actingAs($user)->get(route('candidate-profile.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/candidate')
            ->where('exists', false)
            ->where('profile.full_name', 'Ana Souza')
            ->where('profile.default_output_language', 'pt-BR')
            ->where('profile.cv_convention', 'international')
            ->where('cvConventions.0.value', 'international')
            ->where('cvConventions.1.value', 'us')
            ->where('cvConventions.2.value', 'uk')
            ->has('cvConventions', 3)
            ->where('brazilConvention.label', 'Brasil'));
});

it('saves the candidate profile, keeping a single profile per user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('candidate-profile.update'), validCandidateProfile())
        ->assertRedirect(route('candidate-profile.edit'))
        ->assertInertiaFlash('toast.type', 'success');
    $this->actingAs($user)->put(route('candidate-profile.update'), validCandidateProfile(['headline' => 'Staff Engineer']));

    $profile = CandidateProfile::query()->sole();
    expect($profile->user_id)->toBe($user->id)
        ->and($profile->headline)->toBe('Staff Engineer')
        ->and($profile->links)->toEqual(['linkedin' => 'https://linkedin.com/in/ana', 'github' => 'https://github.com/ana', 'portfolio' => null])
        ->and($profile->default_output_language)->toBe(Language::PtBr)
        ->and($profile->cv_convention)->toBe(CvConvention::UnitedStates);
});

it('saves no convention choice as International / EU', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('candidate-profile.update'), validCandidateProfile(['cv_convention' => '']));

    expect($user->candidateProfile()->sole()->cv_convention)->toBeNull();
});

it('saves the name on files, or none when left blank', function (string $input, ?string $stored, string $nameOnFiles): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('candidate-profile.update'), validCandidateProfile(['full_name' => 'Jonathan Nariai dos Santos', 'file_name' => $input]));

    expect($user->candidateProfile()->sole())
        ->file_name->toBe($stored)
        ->nameOnFiles()->toBe($nameOnFiles);
})->with([
    'chosen' => ['  Jonathan Nariai ', 'Jonathan Nariai', 'Jonathan Nariai'],
    'blank: first name and last surname' => ['', null, 'Jonathan Santos'],
]);

it('suggests first name and last surname as the name on files', function (): void {
    $user = User::factory()->create(['name' => 'Maria da Silva e Souza']);

    $this->actingAs($user)->get(route('candidate-profile.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile.file_name', null)
            ->where('profile.suggested_file_name', 'Maria Souza'));
});

it('validates the profile', function (array $input, string $field): void {
    $this->actingAs(User::factory()->create())
        ->put(route('candidate-profile.update'), validCandidateProfile($input))
        ->assertSessionHasErrors($field);
})->with([
    'name is required' => [['full_name' => ''], 'full_name'],
    'links are URLs' => [['links' => ['linkedin' => 'not a url']], 'links.linkedin'],
    'only http links' => [['links' => ['github' => 'javascript:alert(1)']], 'links.github'],
    'supported output language' => [['default_output_language' => 'fr'], 'default_output_language'],
    'known convention' => [['cv_convention' => 'eu'], 'cv_convention'],
    'name on files at most 60 characters' => [['file_name' => str_repeat('n', 61)], 'file_name'],
    'Brazil is not an English-document convention' => [['cv_convention' => 'brazil'], 'cv_convention'],
]);

it('requires a verified email', function (): void {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('candidate-profile.edit'))
        ->assertRedirect(route('verification.notice'));
});

it('sends guests to the login page', function (): void {
    $this->get(route('candidate-profile.edit'))->assertRedirect(route('login'));
});
