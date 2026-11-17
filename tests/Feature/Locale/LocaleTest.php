<?php

use App\Enums\Language;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

it('uses english when nothing says otherwise', function (): void {
    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.value', 'en'));
});

it('uses the Accept-Language header for guests', function (): void {
    $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9')
        ->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('locale.value', 'pt-BR')
            ->where('translations.Log in', 'Entrar'));
});

it('prefers the guest session choice over the Accept-Language header', function (): void {
    $this->withSession([SetLocale::SESSION_KEY => 'en'])
        ->withHeader('Accept-Language', 'pt-BR')
        ->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.value', 'en'));
});

it("prefers the user's saved language over the session and header", function (): void {
    $user = User::factory()->create(['locale' => Language::PtBr]);

    $this->actingAs($user)
        ->withSession([SetLocale::SESSION_KEY => 'en'])
        ->withHeader('Accept-Language', 'en')
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.value', 'pt-BR'));
});

it('stores a guest language choice in the session', function (): void {
    $this->from(route('login'))
        ->patch(route('locale.update'), ['locale' => 'pt-BR'])
        ->assertRedirect(route('login'))
        ->assertSessionHas(SetLocale::SESSION_KEY, 'pt-BR');
});

it("saves a signed-in user's language choice on the user", function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('locale.update'), ['locale' => 'pt-BR'])
        ->assertRedirect(route('appearance.edit'));

    expect($user->fresh()->locale)->toBe(Language::PtBr);
});

it('rejects an unsupported language', function (): void {
    $this->patch(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

it('signs up a user in the language they were browsing in', function (): void {
    $this->withSession([SetLocale::SESSION_KEY => 'pt-BR'])->post(route('register.store'), [
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::query()->where('email', 'ana@example.com')->firstOrFail()->locale)->toBe(Language::PtBr);
});

it("sends the verification email in the user's language", function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create(['locale' => Language::PtBr]);

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $notification, array $channels, User $notifiable, string $locale): bool => $locale === 'pt_BR');
});

it('shares only the user fields the UI needs', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.name', $user->name)
            ->missing('auth.user.id')
            ->missing('auth.user.password')
            ->missing('auth.user.two_factor_secret'));
});
