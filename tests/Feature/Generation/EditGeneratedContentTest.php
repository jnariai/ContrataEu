<?php

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\GeneratedAnswer;
use App\Models\GeneratedDocument;
use App\Models\Generation;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function generatedCv(string $content = "# Jane Doe\n\nGenerated."): GeneratedDocument
{
    return GeneratedDocument::factory()
        ->for(Generation::factory()->for(Application::factory()))
        ->create(['type' => DocumentType::Cv, 'content' => $content, 'original_content' => $content]);
}

function documentOwner(GeneratedDocument|GeneratedAnswer $model): User
{
    return $model->generation->application->user;
}

it('saves an edited document and keeps the generated text for revert', function (): void {
    $document = generatedCv();

    $this->actingAs(documentOwner($document))
        ->from(route('applications.show', $document->generation->application))
        ->patch(route('generated-documents.update', $document), ['content' => "# Jane Doe\n\nEdited."])
        ->assertRedirect(route('applications.show', $document->generation->application))
        ->assertInertiaFlash('toast.type', 'success');

    $document->refresh();
    expect($document->content)->toBe("# Jane Doe\n\nEdited.")
        ->and($document->original_content)->toBe("# Jane Doe\n\nGenerated.")
        ->and($document->isEdited())->toBeTrue();
});

it('reverts an edited document to the generated text', function (): void {
    $document = generatedCv();
    $document->edit('Edited.');

    $this->actingAs(documentOwner($document))
        ->post(route('generated-documents.revert', $document))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    $document->refresh();
    expect($document->content)->toBe("# Jane Doe\n\nGenerated.")
        ->and($document->isEdited())->toBeFalse();
});

it('does not mark a document as edited when the generated text is saved again', function (): void {
    $document = generatedCv();

    $this->actingAs(documentOwner($document))
        ->patch(route('generated-documents.update', $document), ['content' => "# Jane Doe\n\nGenerated."]);

    expect($document->fresh()->isEdited())->toBeFalse();
});

it('deletes the cached PDF when the document text changes', function (string $route, array $payload): void {
    $document = generatedCv();
    if ($route === 'generated-documents.revert') {
        $document->edit('Edited.');
    }
    $pdf = StoredFile::factory()->forGeneratedDocument($document, documentOwner($document))->create();
    Storage::disk('r2')->put($pdf->path, '%PDF');

    $this->actingAs(documentOwner($document))->{$route === 'generated-documents.revert' ? 'post' : 'patch'}(route($route, $document), $payload);

    expect(StoredFile::query()->count())->toBe(0);
    Storage::disk('r2')->assertMissing($pdf->path);
})->with([
    'edit' => ['generated-documents.update', ['content' => 'Something new.']],
    'revert' => ['generated-documents.revert', []],
]);

it('keeps the cached PDF when the text did not change', function (): void {
    $document = generatedCv();
    $pdf = StoredFile::factory()->forGeneratedDocument($document, documentOwner($document))->create();
    Storage::disk('r2')->put($pdf->path, '%PDF');

    $this->actingAs(documentOwner($document))
        ->patch(route('generated-documents.update', $document), ['content' => $document->content]);

    expect($pdf->fresh())->not->toBeNull();
    Storage::disk('r2')->assertExists($pdf->path);
});

it('validates the edited document', function (mixed $content): void {
    $document = generatedCv();

    $this->actingAs(documentOwner($document))
        ->patch(route('generated-documents.update', $document), ['content' => $content])
        ->assertSessionHasErrors('content');

    expect($document->fresh()->content)->toBe("# Jane Doe\n\nGenerated.");
})->with([
    'empty' => [''],
    'too long' => [str_repeat('a', 50_001)],
]);

it('saves and reverts an edited answer', function (): void {
    $answer = GeneratedAnswer::factory()->create(['content' => 'Generated answer.', 'original_content' => 'Generated answer.']);
    $owner = documentOwner($answer);

    $this->actingAs($owner)->patch(route('generated-answers.update', $answer), ['content' => 'My answer.'])
        ->assertRedirect()->assertInertiaFlash('toast.type', 'success');
    expect($answer->fresh()->content)->toBe('My answer.')
        ->and($answer->fresh()->isEdited())->toBeTrue();

    $this->actingAs($owner)->post(route('generated-answers.revert', $answer))->assertRedirect();
    expect($answer->fresh()->content)->toBe('Generated answer.')
        ->and($answer->fresh()->isEdited())->toBeFalse();
});

it('validates the edited answer', function (): void {
    $answer = GeneratedAnswer::factory()->create();

    $this->actingAs(documentOwner($answer))
        ->patch(route('generated-answers.update', $answer), ['content' => str_repeat('a', 5_001)])
        ->assertSessionHasErrors('content');
});

it("answers 404 when editing another user's document or answer", function (string $method, string $route, Closure $make): void {
    $model = $make();
    $original = $model->content;

    $this->actingAs(User::factory()->create())->{$method}(route($route, $model), ['content' => 'Hijacked.'])
        ->assertNotFound();

    expect($model->fresh()->content)->toBe($original);
})->with([
    'document update' => ['patch', 'generated-documents.update', fn () => generatedCv()],
    'document revert' => ['post', 'generated-documents.revert', fn () => tap(generatedCv())->edit('Edited.')],
    'answer update' => ['patch', 'generated-answers.update', fn () => GeneratedAnswer::factory()->create()],
    'answer revert' => ['post', 'generated-answers.revert', fn () => tap(GeneratedAnswer::factory()->create())->edit('Edited.')],
]);
