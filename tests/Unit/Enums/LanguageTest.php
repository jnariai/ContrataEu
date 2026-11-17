<?php

use App\Enums\Language;

it('picks the first supported language from an Accept-Language header', function (?string $header, Language $expected): void {
    expect(Language::fromAcceptLanguage($header))->toBe($expected);
})->with([
    'brazilian portuguese' => ['pt-BR,pt;q=0.9', Language::PtBr],
    'plain portuguese' => ['pt', Language::PtBr],
    'european portuguese still maps to pt-BR' => ['pt-PT', Language::PtBr],
    'english' => ['en-US,en;q=0.9', Language::En],
    'higher quality wins over order' => ['en;q=0.5,pt-BR;q=0.8', Language::PtBr],
    'unsupported falls back to english' => ['fr', Language::En],
    'unsupported first, supported later' => ['fr-FR,fr;q=0.9,pt;q=0.5', Language::PtBr],
    'q=0 is refused' => ['pt;q=0', Language::En],
    'missing header' => [null, Language::En],
]);

it('has the same section heading keys for every language', function (): void {
    $keys = array_keys(Language::En->sectionHeadings());

    foreach (Language::cases() as $language) {
        expect(array_keys($language->sectionHeadings()))->toBe($keys);
    }
});

it('round-trips between language and laravel locale', function (Language $language): void {
    expect(Language::fromLaravelLocale($language->laravelLocale()))->toBe($language);
})->with(Language::cases());

it('uses ASCII-only cover letter filename suffixes', function (Language $language): void {
    expect($language->coverLetterFilenameSuffix())->toMatch('/^[A-Za-z-]+$/');
})->with(Language::cases());
