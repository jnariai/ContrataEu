<?php

use App\Enums\DocumentType;
use App\ValueObjects\StoragePath;

it('puts uploaded originals under the user, by document type, with a random name', function (): void {
    $first = StoragePath::forMasterOriginal(7, DocumentType::Cv, 'PDF');
    $second = StoragePath::forMasterOriginal(7, DocumentType::Cv, 'pdf');

    expect($first->value)->toMatch('#^users/7/master/cv/[0-9A-Z]{26}\.pdf$#')
        ->and($first->equals($second))->toBeFalse();
});

it('puts rendered PDFs under the user, application and version', function (): void {
    expect(StoragePath::forDocumentPdf(7, 42, 3, DocumentType::CoverLetter)->value)
        ->toBe('users/7/applications/42/v3/cover_letter.pdf');
});

it('refuses a PDF path for a document type without PDF', function (): void {
    StoragePath::forDocumentPdf(7, 42, 1, DocumentType::RecruiterMessage);
})->throws(InvalidArgumentException::class);

it('refuses extensions that could break the key', function (string $extension): void {
    StoragePath::forMasterOriginal(7, DocumentType::Cv, $extension);
})->with(['../pdf', 'p df', '', 'toolongext'])->throws(InvalidArgumentException::class);

it('refuses a non-positive user id', function (): void {
    StoragePath::userDirectory(0);
})->throws(InvalidArgumentException::class);
