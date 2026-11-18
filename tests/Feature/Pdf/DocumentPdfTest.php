<?php

use App\Actions\RenderDocumentPdf;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Enums\Language;
use App\Models\CandidateProfile;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

const EXAMPLE_CV_PT_BR = <<<'MD'
# João Conceição
São Paulo, Brasil · joao@example.com

## Resumo
Desenvolvedor backend com 8 anos de experiência em pagamentos.

## Competências
- **Linguagens:** PHP, TypeScript

## Experiência
### Desenvolvedor Backend Sênior — Ação Pagamentos
*mar 2022 – atual · Remoto*
- Reduzi a latência do checkout em 40%.

## Formação
### Bacharelado em Ciência da Computação — Universidade de São Paulo
*2014 – 2018*
MD;

it('renders the PDF once, keeps it in the bucket and redirects to a signed download URL', function (): void {
    $renderer = fakePdfRenderer();
    $document = pdfDocument();
    $owner = $document->generation->application->user;
    $path = "users/{$owner->id}/applications/{$document->generation->application_id}/v2/cv.pdf";

    $first = $this->actingAs($owner)->get(route('generated-documents.pdf', $document));
    $second = $this->actingAs($owner)->get(route('generated-documents.pdf', $document));

    expect($renderer->calls)->toBe(1);
    Storage::disk('r2')->assertExists($path);
    expect(Storage::disk('r2')->get($path))->toBe('%PDF-1.7 fake');
    $file = StoredFile::query()->sole();
    expect($file->purpose)->toBe(FilePurpose::DocumentPdf)
        ->and($file->path)->toBe($path)
        ->and($file->original_name)->toBe('Jane-Doe-CV.pdf');
    foreach ([$first, $second] as $response) {
        $response->assertRedirect();
        expect($response->headers->get('Location'))->toStartWith("https://r2.test/{$path}?")
            ->toContain('attachment%3B+filename%3DJane-Doe-CV.pdf');
    }
});

it('renders again after the document was edited', function (): void {
    $renderer = fakePdfRenderer();
    $document = pdfDocument();
    $owner = $document->generation->application->user;

    $this->actingAs($owner)->get(route('generated-documents.pdf', $document));
    $this->actingAs($owner)->patch(route('generated-documents.update', $document), ['content' => EXAMPLE_CV."\n- One more line."]);
    $this->actingAs($owner)->get(route('generated-documents.pdf', $document))->assertRedirect();

    expect($renderer->calls)->toBe(2)
        ->and($renderer->html[1])->toContain('One more line.')
        ->and(StoredFile::query()->count())->toBe(1);
});

it('has no PDF for a recruiter message', function (): void {
    $renderer = fakePdfRenderer();
    $document = pdfDocument(DocumentType::RecruiterMessage, content: 'Hi Sam!');

    $this->actingAs($document->generation->application->user)->get(route('generated-documents.pdf', $document))->assertNotFound();

    expect($renderer->calls)->toBe(0);
});

it("answers 404 for another user's document PDF", function (): void {
    $renderer = fakePdfRenderer();
    $document = pdfDocument();

    $this->actingAs(User::factory()->create())->get(route('generated-documents.pdf', $document))->assertNotFound();

    expect($renderer->calls)->toBe(0);
});

it('names the files Name-CV, in ASCII, with the cover letter name in the document language', function (DocumentType $type, Language $language, string $nameOnFiles, string $expected): void {
    $document = pdfDocument($type, $language);
    $document->generation->application->forceFill(['role_title' => 'Programador(a) Full Stack Sênior'])->save();

    expect($document->fresh()->pdfFilename($nameOnFiles))->toBe($expected);
})->with([
    'cv' => [DocumentType::Cv, Language::En, 'Jane Doe', 'Jane-Doe-CV.pdf'],
    'pt-BR cv' => [DocumentType::Cv, Language::PtBr, 'João Conceição', 'Joao-Conceicao-CV.pdf'],
    'cover letter' => [DocumentType::CoverLetter, Language::En, 'Jane Doe', 'Jane-Doe-Cover-Letter.pdf'],
    'pt-BR cover letter' => [DocumentType::CoverLetter, Language::PtBr, 'João Conceição', 'Joao-Conceicao-Carta-de-Apresentacao.pdf'],
]);

it('shortens the name on files to first name and last surname', function (string $fullName, string $expected): void {
    expect(CandidateProfile::shortName($fullName))->toBe($expected);
})->with([
    'brazilian name' => ['Jonathan Nariai dos Santos', 'Jonathan Santos'],
    'particles never become the surname' => ['  Maria  da Silva e Souza ', 'Maria Souza'],
    'a single name' => ['Cher', 'Cher'],
]);

it('names the downloaded PDF with the name on files the candidate chose', function (): void {
    fakePdfRenderer();
    $document = pdfDocument();
    $user = $document->generation->application->user;
    $user->candidateProfile->forceFill(['full_name' => 'Jonathan Nariai dos Santos', 'file_name' => 'Jonathan Nariai'])->save();

    $this->actingAs($user)->get(route('generated-documents.pdf', $document));

    expect($document->fresh()->pdfFile->original_name)->toBe('Jonathan-Nariai-CV.pdf');
});

it('renders the CV view with the name as the only h1, one h2 per section and styled date lines', function (): void {
    $html = app(RenderDocumentPdf::class)->html(pdfDocument());

    expect(substr_count($html, '<h1>'))->toBe(1)
        ->and($html)->toContain('<h1>Jane Doe</h1>')
        ->and(substr_count($html, '<h2>'))->toBe(5)
        ->and($html)->toContain('<h3>Senior Backend Engineer — Foo Payments</h3>')
        ->and($html)->toContain('<p><em>Mar 2022 – Present · Remote</em></p>')
        ->and($html)->toContain('h3 + p')
        ->and($html)->toContain('#1F3A5F')
        ->and($html)->toContain('<html lang="en">')
        ->and($html)->toContain("font-family: 'Inter'")
        ->and($html)->not->toContain('<table')
        ->and($html)->not->toContain('<img')
        ->and($html)->not->toContain('<header')
        ->and($html)->not->toContain('<footer');
});

it('strips raw HTML and unsafe links from the CV', function (): void {
    $html = app(RenderDocumentPdf::class)->html(pdfDocument(content: "# Jane\n\n<script>alert(1)</script><img src=x>\n\n[click](javascript:alert(1))"));

    expect($html)->not->toContain('<script')
        ->and($html)->not->toContain('<img')
        ->and($html)->not->toContain('javascript:');
});

it('renders the cover letter with the contact block, date, recipient and paragraphs', function (): void {
    $document = pdfDocument(DocumentType::CoverLetter, Language::PtBr);
    $document->generation->forceFill(['finished_at' => '2026-11-17 10:00:00'])->save();

    $html = app(RenderDocumentPdf::class)->html($document->fresh());

    expect($html)->toContain('<html lang="pt-BR">')
        ->and($html)->toContain('<h1>Jane Doe</h1>')
        ->and($html)->toContain('Lisbon · jane@example.com · linkedin.com/in/janedoe')
        ->and($html)->toContain('17 de novembro de 2026')
        ->and($html)->toContain('Acme · Senior Backend Engineer')
        ->and(substr_count($html, '<p>'))->toBe(3)
        ->and($html)->toContain('I would love to join.<br>');
});

it('produces a text-based PDF whose text keeps the CV in reading order', function (Language $language, string $cv, array $expected): void {
    $pdf = app(RenderDocumentPdf::class)->handle(pdfDocument(language: $language, content: $cv));

    expect($pdf)->toStartWith('%PDF-');
    $text = pdfText($pdf);
    $position = -1;
    foreach ($expected as $needle) {
        $next = mb_strpos($text, $needle);
        expect($next)->not->toBeFalse("“{$needle}” is missing from the PDF text:\n{$text}")
            ->and($next)->toBeGreaterThan($position, "“{$needle}” is out of order in the PDF text:\n{$text}");
        $position = $next;
    }
})->with([
    'en' => [Language::En, EXAMPLE_CV, ['Jane Doe', 'Summary', 'Skills', 'Experience', 'Senior Backend Engineer — Foo Payments', 'Mar 2022 – Present', 'Backend Engineer — Bar Bank', 'Projects', 'Ledgerly', 'Education', 'BSc Computer Science']],
    'pt-BR' => [Language::PtBr, EXAMPLE_CV_PT_BR, ['João Conceição', 'Resumo', 'Competências', 'Experiência', 'Desenvolvedor Backend Sênior — Ação Pagamentos', 'Formação', 'Bacharelado em Ciência da Computação']],
]);
