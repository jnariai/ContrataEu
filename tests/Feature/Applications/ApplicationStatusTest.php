<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Carbon\CarbonImmutable;

it('records when the application was first sent', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00'));
    $application = Application::factory()->inStatus(ApplicationStatus::Ready)->create();

    $this->actingAs($application->user)
        ->from(route('applications.show', $application))
        ->patch(route('application-statuses.update', $application), ['status' => 'applied'])
        ->assertRedirect(route('applications.show', $application))
        ->assertInertiaFlash('toast.type', 'success');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Applied)
        ->and($application->fresh()->applied_at?->toIso8601ZuluString())->toBe('2026-11-10T09:00:00Z');
});

it('keeps the first applied date when the status moves on and comes back', function (): void {
    $application = Application::factory()->inStatus(ApplicationStatus::Ready)->create();

    $application->changeStatus(ApplicationStatus::Applied, CarbonImmutable::parse('2026-11-10 09:00'));
    $application->changeStatus(ApplicationStatus::Interviewing, CarbonImmutable::parse('2026-11-12 09:00'));
    $application->changeStatus(ApplicationStatus::Applied, CarbonImmutable::parse('2026-11-14 09:00'));

    expect($application->fresh()->applied_at?->toDateString())->toBe('2026-11-10');
});

it('does not set an applied date for other statuses', function (): void {
    $application = Application::factory()->create();

    $application->changeStatus(ApplicationStatus::Withdrawn, CarbonImmutable::parse('2026-11-10 09:00'));

    expect($application->fresh()->applied_at)->toBeNull();
});

it('rejects an unknown status', function (): void {
    $application = Application::factory()->create();

    $this->actingAs($application->user)
        ->patch(route('application-statuses.update', $application), ['status' => 'hired'])
        ->assertSessionHasErrors('status');
});

it("answers 404 when changing another user's application", function (): void {
    $application = Application::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('application-statuses.update', $application), ['status' => 'applied'])
        ->assertNotFound();
});
