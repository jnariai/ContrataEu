<?php

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
        'region_convention' => 'EU',
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
            ->where('profile.default_output_language', 'pt-BR'));
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
        ->and($profile->default_output_language)->toBe(Language::PtBr);
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
]);

it('requires a verified email', function (): void {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('candidate-profile.edit'))
        ->assertRedirect(route('verification.notice'));
});

it('sends guests to the login page', function (): void {
    $this->get(route('candidate-profile.edit'))->assertRedirect(route('login'));
});
