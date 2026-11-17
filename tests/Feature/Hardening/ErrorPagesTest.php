<?php

use App\Enums\DocumentType;
use App\Enums\Language;
use App\Gateways\Cloudflare\CloudflareException;
use App\Gateways\PdfRenderer;
use App\Models\Application;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function failingPdfDocument(): GeneratedDocument
{
    app()->instance(PdfRenderer::class, new class implements PdfRenderer
    {
        public function render(string $html): string
        {
            throw CloudflareException::badResponse(500);
        }
    });

    return GeneratedDocument::factory()
        ->for(Generation::factory()->for(Application::factory()))
        ->create(['type' => DocumentType::Cv]);
}

it('shows the error page with a safe message when a PDF download fails', function (): void {
    $document = failingPdfDocument();

    $this->actingAs($document->generation->application->user)
        ->get(route('generated-documents.pdf', $document))
        ->assertStatus(502)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->where('status', 502)
            ->where('message', 'A service we rely on isn’t responding right now. Please try again in a few minutes.')
            ->has('translations')
            ->where('auth.user.email', $document->generation->application->user->email));
});

it('shows the error page in the user’s language', function (): void {
    $document = failingPdfDocument();
    $document->generation->application->user->forceFill(['locale' => Language::PtBr])->save();

    $this->actingAs($document->generation->application->user->fresh())
        ->get(route('generated-documents.pdf', $document))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->where('message', __('A service we rely on isn’t responding right now. Please try again in a few minutes.', locale: 'pt_BR')));
});

it('shows the error page for a missing page when debug is off', function (): void {
    config(['app.debug' => false]);

    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->where('status', 404)
            ->where('message', null));
});

it("shows the error page for another user's application when debug is off", function (): void {
    config(['app.debug' => false]);
    $application = Application::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('applications.show', $application))
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('status', 404));
});

it('keeps Laravel’s debug page for unexpected errors while debugging', function (): void {
    config(['app.debug' => true]);

    $response = $this->get('/this-page-does-not-exist')->assertNotFound();

    expect($response->headers->get('X-Inertia'))->toBeNull()
        ->and($response->getContent())->not->toContain('"component":"error"');
});

it('keeps JSON errors for API callers', function (): void {
    config(['app.debug' => false]);

    $this->getJson('/this-page-does-not-exist')->assertNotFound()->assertJsonMissingPath('component');
});

it('stays on the page with a toast after too many writes in a minute', function (): void {
    $application = Application::factory()->create();
    $this->actingAs($application->user)->from(route('applications.show', $application));

    foreach (range(1, 60) as $attempt) {
        $this->patch(route('application-statuses.update', $application), ['status' => 'applied'], ['X-Inertia' => 'true'])
            ->assertRedirect();
    }

    $this->patch(route('application-statuses.update', $application), ['status' => 'applied'], ['X-Inertia' => 'true'])
        ->assertRedirect(route('applications.show', $application))
        ->assertInertiaFlash('toast.type', 'error');
});

it('does not count page loads against the writes limit', function (): void {
    $application = Application::factory()->create();

    foreach (range(1, 70) as $attempt) {
        $this->actingAs($application->user)->get(route('applications.show', $application))->assertOk();
    }
});

it('shows an unknown URL as not found in the signed-in user’s language', function (): void {
    config(['app.debug' => false]);
    $user = User::factory()->create(['locale' => Language::PtBr]);

    $this->actingAs($user)->get('/nao-existe')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('error')
            ->where('locale.value', 'pt-BR')
            ->where('auth.user.email', $user->email));
});
