<?php

use App\Enums\PromptKey;
use App\Models\PromptOverride;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('ships a non-empty default for every prompt section', function (PromptKey $key): void {
    expect(trim($key->defaultContent()))->not->toBeEmpty();
})->with(PromptKey::cases());

it('shows each section with its default and the user override', function (): void {
    $user = User::factory()->create();
    PromptOverride::factory()->for($user)->create(['key' => PromptKey::Playbook, 'content' => 'My rules']);

    $this->actingAs($user)->get(route('prompt-overrides.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/prompts')
            ->has('prompts', count(PromptKey::cases()))
            ->where('prompts.1.key', 'playbook')
            ->where('prompts.1.override', 'My rules')
            ->where('prompts.1.default', PromptKey::Playbook->defaultContent())
            ->where('prompts.0.override', null));
});

it('saves an override and replaces it on the next save', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('prompt-overrides.update', 'cv_instructions'), ['content' => 'First']);
    $this->actingAs($user)->put(route('prompt-overrides.update', 'cv_instructions'), ['content' => 'Second'])
        ->assertRedirect(route('prompt-overrides.index'));

    expect(PromptOverride::query()->sole()->content)->toBe('Second');
});

it('resets a section to the default', function (): void {
    $user = User::factory()->create();
    PromptOverride::factory()->for($user)->create(['key' => PromptKey::System]);
    $theirs = PromptOverride::factory()->create(['key' => PromptKey::System]);

    $this->actingAs($user)->delete(route('prompt-overrides.destroy', 'system'))->assertRedirect(route('prompt-overrides.index'));

    expect(PromptOverride::query()->pluck('id')->all())->toBe([$theirs->id]);
});

it('does not accept an empty override (reset instead)', function (): void {
    $this->actingAs(User::factory()->create())
        ->put(route('prompt-overrides.update', 'system'), ['content' => ''])
        ->assertSessionHasErrors('content');
});

it('answers 404 for an unknown section', function (): void {
    $this->actingAs(User::factory()->create())
        ->put('/settings/prompts/output_schema', ['content' => 'x'])
        ->assertNotFound();
});
