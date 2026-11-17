<?php

use App\Enums\DocumentType;
use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

it('lists the three documents, with the CV required and any attached original', function (): void {
    $user = User::factory()->create();
    $cv = MasterDocument::factory()->for($user)->create(['content' => '# Ana']);
    $file = StoredFile::factory()->forMasterDocument($cv)->create(['original_name' => 'cv.pdf']);

    $this->actingAs($user)->get(route('master-documents.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/documents')
            ->has('documents', 3)
            ->where('documents.0.type', 'cv')
            ->where('documents.0.required', true)
            ->where('documents.0.content', '# Ana')
            ->where('documents.0.file.id', $file->public_id)
            ->missing('documents.0.file.path')
            ->where('documents.1.content', null));
});

it('saves the master CV', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('master-documents.update', 'cv'), ['content' => "# Ana Souza\n\n## Experience"])
        ->assertRedirect(route('master-documents.index'));

    expect(MasterDocument::findFor($user, DocumentType::Cv)?->content)->toBe("# Ana Souza\n\n## Experience");
});

it('does not accept an empty CV', function (): void {
    $this->actingAs(User::factory()->create())
        ->put(route('master-documents.update', 'cv'), ['content' => ''])
        ->assertSessionHasErrors('content');
});

it('removes an optional document and its original file when its text is cleared', function (): void {
    $user = User::factory()->create();
    $letter = MasterDocument::factory()->for($user)->coverLetter()->create();
    $file = StoredFile::factory()->forMasterDocument($letter)->create();
    Storage::disk('r2')->put($file->path, '%PDF');

    $this->actingAs($user)->put(route('master-documents.update', 'cover_letter'), ['content' => ''])
        ->assertRedirect(route('master-documents.index'));

    expect(MasterDocument::query()->count())->toBe(0)->and(StoredFile::query()->count())->toBe(0);
    Storage::disk('r2')->assertMissing($file->path);
});

it('answers 404 for an unknown document type', function (): void {
    $this->actingAs(User::factory()->create())
        ->put('/settings/documents/diploma', ['content' => 'x'])
        ->assertNotFound();
});

it('attaches the original file under the user prefix with a random name, replacing the previous one', function (): void {
    $user = User::factory()->create();
    $cv = MasterDocument::factory()->for($user)->create();
    $old = StoredFile::factory()->forMasterDocument($cv)->create();
    Storage::disk('r2')->put($old->path, '%PDF-old');

    $this->actingAs($user)
        ->post(route('master-document-files.store', 'cv'), ['file' => UploadedFile::fake()->createWithContent('../../evil name.pdf', "%PDF-1.7\n%test")])
        ->assertRedirect(route('master-documents.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $new = StoredFile::query()->sole();
    expect($new->path)->toMatch("#^users/{$user->id}/master/cv/[0-9A-Z]{26}\.pdf$#")
        ->and($new->original_name)->toBe('evil name.pdf'); // Laravel strips path segments from client names
    Storage::disk('r2')->assertExists($new->path);
    Storage::disk('r2')->assertMissing($old->path);
});

it('asks for the text before accepting an original file', function (): void {
    $this->actingAs(User::factory()->create())
        ->from(route('master-documents.index'))
        ->post(route('master-document-files.store', 'cv'), ['file' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.7\n%test")])
        ->assertRedirect(route('master-documents.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(StoredFile::query()->count())->toBe(0);
});

it('accepts only PDF or DOCX files up to 5 MB', function (UploadedFile $file): void {
    $user = User::factory()->create();
    MasterDocument::factory()->for($user)->create();

    $this->actingAs($user)->post(route('master-document-files.store', 'cv'), ['file' => $file])
        ->assertSessionHasErrors('file');
})->with([
    'an image' => fn () => UploadedFile::fake()->image('cv.png'),
    // A real temp file, so the type is detected from its content like a real upload (fakes report the name's type).
    'a script renamed to pdf' => function (): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<?php echo "hi";');

        return new UploadedFile($path, 'cv.pdf', null, null, true);
    },
    'over 5 MB' => fn () => UploadedFile::fake()->create('cv.pdf', 5 * 1024 + 1, 'application/pdf'),
]);

it('removes the original file', function (): void {
    $user = User::factory()->create();
    $file = StoredFile::factory()->forMasterDocument(MasterDocument::factory()->for($user)->create())->create();
    Storage::disk('r2')->put($file->path, '%PDF');

    $this->actingAs($user)->delete(route('master-document-files.destroy', 'cv'))
        ->assertRedirect(route('master-documents.index'));

    expect(StoredFile::query()->count())->toBe(0);
    Storage::disk('r2')->assertMissing($file->path);
});

it("never touches another user's document", function (): void {
    $other = User::factory()->create();
    $theirs = MasterDocument::factory()->for($other)->create(['content' => 'theirs']);
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('master-documents.update', 'cv'), ['content' => 'mine']);

    expect($theirs->fresh()->content)->toBe('theirs')
        ->and(MasterDocument::findFor($user, DocumentType::Cv)?->content)->toBe('mine');
});
