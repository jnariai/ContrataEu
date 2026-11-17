<?php

use App\Actions\RunCvImport;
use App\Enums\AiProvider;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Enums\GenerationStatus;
use App\Jobs\RunCvImportJob;
use App\Models\AiCredential;
use App\Models\CvImport;
use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\Models\User;
use App\ValueObjects\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\ValueObjects\Media\Document;

const IMPORTED_CV = "# Jane Doe\nLisbon, Portugal · jane@example.com\n\n## Summary\nBackend engineer, 8 years.";

function cvPdf(string $name = 'jane-cv.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cv');
    file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

    return new UploadedFile($path, $name, 'application/pdf', test: true);
}

function userWithKey(AiProvider $provider = AiProvider::Anthropic): User
{
    $user = User::factory()->create();
    AiCredential::factory()->for($user)->asDefault()->create(['provider' => $provider, 'api_key' => 'sk-user-key-1234', 'model' => $provider->defaultModel()]);

    return $user;
}

/** A queued import with its PDF in the (fake) bucket, as StartCvImport leaves it. */
function queuedCvImport(?User $user = null): CvImport
{
    $user ??= userWithKey();
    $import = CvImport::factory()->for($user)->queued()->create(['provider' => AiProvider::Anthropic, 'model' => 'claude-sonnet-5-5']);
    $path = StoragePath::forMasterOriginal($user->id, DocumentType::Cv, 'pdf');
    Storage::disk('r2')->put($path->value, '%PDF-1.4 jane');
    StoredFile::record($user, $import, FilePurpose::MasterOriginal, 'r2', $path, 'application/pdf', 13, 'jane-cv.pdf');

    return $import;
}

it('stores the PDF and queues the AI read', function (): void {
    Queue::fake();
    $user = userWithKey();

    $this->actingAs($user)->post(route('cv-imports.store'), ['file' => cvPdf()])
        ->assertRedirect(route('master-documents.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $import = CvImport::query()->sole();
    $file = $import->pdfFile;
    expect($import->status)->toBe(GenerationStatus::Queued)
        ->and($import->provider)->toBe(AiProvider::Anthropic)
        ->and($file->path)->toStartWith("users/{$user->id}/master/cv/")
        ->and($file->original_name)->toBe('jane-cv.pdf');
    Storage::disk('r2')->assertExists($file->path);
    Queue::assertPushed(RunCvImportJob::class, fn (RunCvImportJob $job) => $job->import->is($import));
});

it('only accepts PDFs up to 5 MB', function (UploadedFile $file): void {
    Queue::fake();

    $this->actingAs(userWithKey())->post(route('cv-imports.store'), ['file' => $file])->assertSessionHasErrors('file');

    expect(CvImport::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'a Word file' => [fn () => UploadedFile::fake()->create('cv.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
    'too big' => [fn () => UploadedFile::fake()->create('cv.pdf', 6 * 1024, 'application/pdf')],
]);

it('refuses to start without a key that can read PDFs', function (?AiProvider $provider, string $message): void {
    Queue::fake();
    $user = $provider === null ? User::factory()->create() : userWithKey($provider);

    $this->actingAs($user)->post(route('cv-imports.store'), ['file' => cvPdf()])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', $message);

    expect(CvImport::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no key' => [null, 'Add an AI key first (Settings → AI keys). Your AI account reads the PDF.'],
    'Groq' => [AiProvider::Groq, 'Groq can’t read PDFs. Make an Anthropic, OpenAI, Gemini or OpenRouter key your default, or paste your CV as text.'],
    'DeepSeek' => [AiProvider::DeepSeek, 'DeepSeek can’t read PDFs. Make an Anthropic, OpenAI, Gemini or OpenRouter key your default, or paste your CV as text.'],
]);

it('runs one import at a time per user', function (): void {
    Queue::fake();
    $import = queuedCvImport();

    $this->actingAs($import->user)->post(route('cv-imports.store'), ['file' => cvPdf()])
        ->assertInertiaFlash('toast.message', 'Your CV is already being read. Wait for it to finish, then try again.');

    expect(CvImport::query()->count())->toBe(1);
});

it('replaces an older result nobody saved, with its PDF', function (): void {
    Queue::fake();
    $old = queuedCvImport();
    $old->succeed(IMPORTED_CV, now()->toImmutable());
    $oldPath = $old->pdfFile->path;

    $this->actingAs($old->user)->post(route('cv-imports.store'), ['file' => cvPdf()]);

    expect(CvImport::query()->pluck('id')->all())->not->toContain($old->id)
        ->and(CvImport::query()->count())->toBe(1);
    Storage::disk('r2')->assertMissing($oldPath);
});

it('reads the PDF with the user’s key and keeps the Markdown', function (): void {
    $import = queuedCvImport();
    $fake = Prism::fake([TextResponseFake::make()->withText("```markdown\n".IMPORTED_CV."\n```")]);

    app(RunCvImport::class)->handle($import);

    $import->refresh();
    expect($import->status)->toBe(GenerationStatus::Succeeded)
        ->and($import->markdown)->toBe(IMPORTED_CV);
    $fake->assertProviderConfig(['api_key' => 'sk-user-key-1234']);
    $fake->assertRequest(function (array $requests): void {
        /** @var TextRequest $request */
        $request = $requests[0];
        $documents = collect($request->messages()[0]->additionalContent)->filter(fn ($part) => $part instanceof Document);

        expect($request->model())->toBe('claude-sonnet-5-5')
            ->and($documents)->toHaveCount(1)
            ->and($documents->first()->mimeType())->toBe('application/pdf')
            ->and($documents->first()->rawContent())->toBe('%PDF-1.4 jane')
            ->and(collect($request->systemPrompts())->implode('content', "\n"))->toContain('Never invent');
    });
});

it('fails with an actionable message', function (Closure $arrange, string $message): void {
    $import = queuedCvImport();
    $arrange($import);

    app(RunCvImport::class)->handle($import);

    expect($import->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($import->fresh()->error)->toBe($message)
        ->and($import->fresh()->markdown)->toBeNull();
})->with([
    'not a CV' => [fn () => Prism::fake([TextResponseFake::make()->withText('NO_CV')]), 'No CV text could be read from this PDF. Check that it is your CV (not a scan of a photo), or paste the text instead.'],
    'no name heading' => [fn () => Prism::fake([TextResponseFake::make()->withText('Here is your CV: lots of text')]), 'No CV text could be read from this PDF. Check that it is your CV (not a scan of a photo), or paste the text instead.'],
    'key removed' => [fn (CvImport $import) => $import->user->aiCredentials()->delete(), 'Your Anthropic key was removed before the PDF could be read. Add it again, then upload the PDF again.'],
    'PDF gone' => [fn (CvImport $import) => Storage::disk('r2')->delete($import->pdfFile->path), 'The uploaded PDF is gone. Upload it again.'],
]);

it('does not run an import twice', function (): void {
    $import = queuedCvImport();
    $import->start(now()->toImmutable());
    $fake = Prism::fake([TextResponseFake::make()->withText(IMPORTED_CV)]);

    app(RunCvImport::class)->handle($import);

    $fake->assertCallCount(0);
});

it('marks the import failed when the worker gives up', function (): void {
    $import = queuedCvImport();

    (new RunCvImportJob($import))->failed(new RuntimeException('Timed out'));

    expect($import->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($import->fresh()->error)->toBe('Something went wrong while reading your PDF. Please try again, or paste your CV as text.');
});

it('shows the newest import and whether the default key can read PDFs', function (): void {
    $import = queuedCvImport();
    $import->succeed(IMPORTED_CV, now()->toImmutable());

    $this->actingAs($import->user)->get(route('master-documents.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cvImport.id', $import->public_id)
            ->where('cvImport.markdown', IMPORTED_CV)
            ->where('cvImport.file_name', 'jane-cv.pdf')
            ->where('canImportCv.allowed', true));

    $this->actingAs(userWithKey(AiProvider::Groq))->get(route('master-documents.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('cvImport', null)
            ->where('canImportCv.allowed', false)
            ->where('canImportCv.reason', 'Groq can’t read PDFs. Make an Anthropic, OpenAI, Gemini or OpenRouter key your default to import one.'));
});

it('saves the imported CV and keeps the PDF as its original file, replacing the old one', function (): void {
    $import = queuedCvImport();
    $import->succeed(IMPORTED_CV, now()->toImmutable());
    $user = $import->user;
    $pdf = $import->pdfFile;
    $master = MasterDocument::factory()->for($user)->create(['type' => DocumentType::Cv]);
    $old = StoredFile::factory()->forMasterDocument($master)->create(['user_id' => $user->id]);
    Storage::disk('r2')->put($old->path, 'old');

    $this->actingAs($user)->put(route('master-documents.update', 'cv'), ['content' => IMPORTED_CV."\n\nEdited.", 'import' => $import->public_id])
        ->assertRedirect(route('master-documents.index'))
        ->assertSessionHasNoErrors();

    $master->refresh()->load('originalFile');
    expect($master->content)->toBe(IMPORTED_CV."\n\nEdited.")
        ->and($master->originalFile->id)->toBe($pdf->id)
        ->and(CvImport::query()->count())->toBe(0)
        ->and(StoredFile::query()->count())->toBe(1);
    Storage::disk('r2')->assertExists($pdf->path);
    Storage::disk('r2')->assertMissing($old->path);
});

it('creates the master CV from an import on first save', function (): void {
    $import = queuedCvImport();
    $import->succeed(IMPORTED_CV, now()->toImmutable());
    $pdfId = $import->pdfFile->id;

    $this->actingAs($import->user)->put(route('master-documents.update', 'cv'), ['content' => IMPORTED_CV, 'import' => $import->public_id]);

    expect(MasterDocument::findFor($import->user, DocumentType::Cv)?->originalFile?->id)->toBe($pdfId);
});

it('only adopts the user’s own finished import, and only for the CV', function (Closure $makeImport, string $type): void {
    $user = userWithKey();
    $import = $makeImport($user);

    $this->actingAs($user)->put(route('master-documents.update', $type), ['content' => IMPORTED_CV, 'import' => $import->public_id])
        ->assertSessionHasErrors('import');

    expect(CvImport::query()->whereKey($import->id)->exists())->toBeTrue();
})->with([
    'another user’s import' => [fn () => CvImport::factory()->create(), 'cv'],
    'still running' => [fn (User $user) => CvImport::factory()->for($user)->queued()->create(), 'cv'],
    'on the cover letter' => [fn (User $user) => CvImport::factory()->for($user)->create(), 'cover_letter'],
]);

it('discards an import with its PDF', function (): void {
    $import = queuedCvImport();
    $import->succeed(IMPORTED_CV, now()->toImmutable());
    $path = $import->pdfFile->path;

    $this->actingAs($import->user)->delete(route('cv-imports.destroy', $import))
        ->assertRedirect(route('master-documents.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(CvImport::query()->count())->toBe(0)->and(StoredFile::query()->count())->toBe(0);
    Storage::disk('r2')->assertMissing($path);
});

it("answers 404 for another user's import", function (): void {
    $import = queuedCvImport();

    $this->actingAs(User::factory()->create())->delete(route('cv-imports.destroy', $import))->assertNotFound();

    expect($import->fresh())->not->toBeNull();
});

it('prunes imports nobody saved after a day, with their PDF', function (): void {
    $stale = queuedCvImport();
    $stale->succeed(IMPORTED_CV, now()->toImmutable());
    $stale->forceFill(['created_at' => now()->subHours(25)])->save();
    $path = $stale->pdfFile->path;
    $fresh = CvImport::factory()->create();

    $this->artisan('model:prune', ['--model' => [CvImport::class]])->assertSuccessful();

    expect(CvImport::query()->pluck('id')->all())->toBe([$fresh->id]);
    Storage::disk('r2')->assertMissing($path);
});
