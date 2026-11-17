<?php

use App\Actions\StoreFile;
use App\Enums\DocumentType;
use App\Enums\FilePurpose;
use App\Models\GeneratedDocument;
use App\Models\MasterDocument;
use App\Models\StoredFile;
use App\ValueObjects\StoragePath;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->disk = fakeFilesDisk();
});

it('writes an upload to the bucket and tracks it with the name, type and size the user sent', function (): void {
    $document = MasterDocument::factory()->create();
    $path = StoragePath::forMasterOriginal($document->user_id, DocumentType::Cv, 'pdf');

    $file = app(StoreFile::class)->handle($document->user, $document, FilePurpose::MasterOriginal, $path, UploadedFile::fake()->create('My CV.pdf', 120, 'application/pdf'));

    $this->disk->assertExists($path->value);
    expect($file->path)->toBe($path->value)
        ->and($file->original_name)->toBe('My CV.pdf')
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->size_bytes)->toBe(120 * 1024)
        ->and($file->user_id)->toBe($document->user_id)
        ->and($document->originalFile()->first()?->is($file))->toBeTrue();
});

it('replaces the previous file for the same purpose and deletes its object', function (): void {
    $document = MasterDocument::factory()->create();
    $store = app(StoreFile::class);
    $old = $store->handle($document->user, $document, FilePurpose::MasterOriginal, StoragePath::forMasterOriginal($document->user_id, DocumentType::Cv, 'pdf'), UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'));

    $new = $store->handle($document->user, $document, FilePurpose::MasterOriginal, StoragePath::forMasterOriginal($document->user_id, DocumentType::Cv, 'pdf'), UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'));

    $this->disk->assertMissing($old->path);
    $this->disk->assertExists($new->path);
    expect(StoredFile::query()->pluck('id')->all())->toBe([$new->id]);
});

it('overwrites a file stored again at the same path, keeping one row', function (): void {
    $document = GeneratedDocument::factory()->create();
    $owner = $document->generation->application->user;
    $path = StoragePath::forDocumentPdf($owner->id, $document->generation->application_id, 1, DocumentType::Cv);
    $store = app(StoreFile::class);

    $first = $store->handle($owner, $document, FilePurpose::DocumentPdf, $path, '%PDF-first');
    $second = $store->handle($owner, $document, FilePurpose::DocumentPdf, $path, '%PDF-second-version', 'CV.pdf');

    expect($second->is($first))->toBeTrue()
        ->and($second->size_bytes)->toBe(strlen('%PDF-second-version'))
        ->and(StoredFile::query()->count())->toBe(1)
        ->and($this->disk->get($path->value))->toBe('%PDF-second-version');
});

it('deletes the object when the file row is deleted', function (): void {
    $document = MasterDocument::factory()->create();
    $file = app(StoreFile::class)->handle($document->user, $document, FilePurpose::MasterOriginal, StoragePath::forMasterOriginal($document->user_id, DocumentType::Cv, 'pdf'), '%PDF-1.7');

    $file->delete();

    $this->disk->assertMissing($file->path);
});
