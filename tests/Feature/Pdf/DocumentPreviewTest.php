<?php

use App\Actions\RenderDocumentPdf;
use App\Enums\DocumentType;
use App\Enums\Language;
use App\Models\CandidateProfile;
use App\Models\User;

it('previews the master CV in the PDF template, unsaved text included', function (): void {
    $user = User::factory()->create(['name' => 'Account Name']);
    CandidateProfile::factory()->for($user)->create(['full_name' => 'Jane Doe', 'default_output_language' => Language::PtBr]);

    $response = $this->actingAs($user)->postJson(route('master-cv-previews.store'), ['content' => "# Jane Doe\n\n## Resumo\nUnsaved text.\n\n<script>alert(1)</script>"])
        ->assertOk();

    $html = $response->json('html');
    expect($html)->toContain('<html lang="pt-BR">')
        ->toContain('<h1>Jane Doe</h1>')
        ->toContain('Unsaved text.')
        ->toContain('width: 210mm')
        ->toContain(route('pdf-fonts.show', 'Inter-Regular.ttf'))
        ->not->toContain('data:font/ttf;base64')
        ->not->toContain('<script');
});

it('requires text to preview', function (): void {
    $this->actingAs(User::factory()->create())->postJson(route('master-cv-previews.store'), ['content' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content');
});

it('previews a generated document with the editor’s unsaved text', function (DocumentType $type, string $content, string $expected): void {
    $document = pdfDocument($type);

    $this->actingAs($document->generation->application->user)
        ->postJson(route('generated-documents.preview', $document), ['content' => $content])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, $expected) && str_contains($html, 'width: 210mm'));
})->with([
    'CV' => [DocumentType::Cv, "# Jane Doe\n\n## Summary\nNot saved yet.", 'Not saved yet.'],
    'cover letter' => [DocumentType::CoverLetter, "Dear Acme,\n\nNot saved yet.", 'Not saved yet.'],
]);

it('has no preview for a recruiter message', function (): void {
    $document = pdfDocument(DocumentType::RecruiterMessage, content: 'Hi Sam!');

    $this->actingAs($document->generation->application->user)
        ->postJson(route('generated-documents.preview', $document), ['content' => 'Hi'])
        ->assertNotFound();
});

it("answers 404 for another user's document preview", function (): void {
    $document = pdfDocument();

    $this->actingAs(User::factory()->create())
        ->postJson(route('generated-documents.preview', $document), ['content' => 'x'])
        ->assertNotFound();
});

it('keeps the PDF itself self-contained, without screen styles', function (): void {
    $html = app(RenderDocumentPdf::class)->html(pdfDocument());

    expect($html)->toContain('data:font/ttf;base64')
        ->not->toContain('width: 210mm')
        ->not->toContain('pdf-fonts');
});

it('serves the template fonts to the preview with CORS', function (): void {
    $response = $this->get(route('pdf-fonts.show', 'Inter-Bold.ttf'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('font/ttf')
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('*')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable');
});

it('serves only the template fonts', function (string $font): void {
    $this->get('/pdf-fonts/'.$font)->assertNotFound();
})->with(['unknown font' => ['Comic-Sans.ttf'], 'licence file' => ['OFL.txt'], 'path traversal' => ['..%2F..%2F.env']]);
