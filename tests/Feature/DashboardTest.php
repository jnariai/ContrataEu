<?php

use App\Enums\ApplicationStatus;
use App\Models\AiCredential;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\MasterDocument;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows the setup checklist until profile, master CV and AI key exist', function (): void {
    $user = User::factory()->create();
    CandidateProfile::factory()->for($user)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('setup.profile', true)
            ->where('setup.masterCv', false)
            ->where('setup.aiKey', false)
            ->where('setup.complete', false));

    MasterDocument::factory()->for($user)->create();
    AiCredential::factory()->for($user)->asDefault()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('setup.complete', true));
});

it("lists only the user's applications with counts per status", function (): void {
    $user = User::factory()->create();
    Application::factory()->for($user)->count(2)->create();
    Application::factory()->for($user)->applied()->create();
    Application::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('applications.data', 3)
            ->where('counts', ['draft' => 2, 'applied' => 1])
            ->missing('applications.data.0.user_id'));
});

it('filters applications by status, keeping the filter in the query string', function (): void {
    $user = User::factory()->create();
    Application::factory()->for($user)->create();
    $applied = Application::factory()->for($user)->applied()->create();

    $this->actingAs($user)->get(route('dashboard', ['status' => ApplicationStatus::Applied->value]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.status', 'applied')
            ->has('applications.data', 1)
            ->where('applications.data.0.id', $applied->public_id));
});

it('ignores an unknown status filter', function (): void {
    $user = User::factory()->create();
    Application::factory()->for($user)->count(2)->create();

    $this->actingAs($user)->get(route('dashboard', ['status' => 'nope']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.status', null)->has('applications.data', 2));
});
