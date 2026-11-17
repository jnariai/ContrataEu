<?php

use App\Enums\Language;
use App\ValueObjects\CvMarkdown;

$valid = <<<'MD'
# Jane Doe
Lisbon, Portugal · jane@example.com · [github.com/jane](https://github.com/jane)

## Summary
Backend engineer, 7 years, payments.

## Experience
### Backend Engineer — Acme
*Mar 2022 – Present · Remote*
- Built the billing platform.

## Projects
### Ledger — [link](https://github.com/jane/ledger)
- Open-source double-entry ledger.

## Education
### BSc Computer Science — University of Lisbon

*2014 – 2018*
MD;

it('accepts the example structure from cv-output-format.md', function () use ($valid): void {
    expect(CvMarkdown::fromString($valid)->warnings(Language::En))->toBe([]);
});

it('accepts pt-BR headings for a pt-BR CV and flags English ones in it', function () use ($valid): void {
    $portuguese = strtr($valid, ['## Summary' => '## Resumo', '## Experience' => '## Experiência', '## Projects' => '## Projetos', '## Education' => '## Formação']);

    expect(CvMarkdown::fromString($portuguese)->warnings(Language::PtBr))->toBe([])
        ->and(CvMarkdown::fromString($valid)->warnings(Language::PtBr))->toHaveCount(4)
        ->and(CvMarkdown::fromString($portuguese)->warnings(Language::En))->toHaveCount(4);
});

it('flags what breaks the template', function (string $markdown): void {
    expect(CvMarkdown::fromString($markdown)->warnings(Language::En))->toHaveCount(1);
})->with([
    'two names' => ["# Jane\n# Doe\n## Skills\n- PHP"],
    'no name' => ["## Skills\n- PHP"],
    'a non-standard section' => ["# Jane\n## Hobbies\n- Chess"],
    'an experience entry without a date line' => ["# Jane\n## Experience\n### Dev — Acme\n- Built things"],
    'a table' => ["# Jane\n## Skills\n| Lang | Years |\n|---|---|"],
    'raw HTML' => ["# Jane\n## Skills\n<b>PHP</b>"],
    'an image' => ["# Jane\n## Skills\n![logo](https://x.test/logo.png)"],
]);

it('lists the TODO placeholders left in the text', function (): void {
    $cv = CvMarkdown::fromString("- Cut costs by [TODO: metric?].\n- Led [TODO: team size] people.\n- Again [TODO: metric?].");

    expect($cv->todos())->toBe(['[TODO: metric?]', '[TODO: team size]']);
});
