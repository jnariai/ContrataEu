<?php

namespace App\ValueObjects;

use App\Enums\Language;

/**
 * A CV in the app-owned Markdown source structure (resources/prompts/app/cv-source-structure.md), which the PDF
 * template depends on. `warnings()` lists what breaks the structure; they are shown to the user (like TODOs),
 * never treated as errors, because the user may edit the text freely.
 */
final readonly class CvMarkdown
{
    /** Sections whose `###` entries must have a date line right below. */
    private const array DATED_SECTIONS = ['experience', 'education'];

    private function __construct(public string $markdown) {}

    public static function fromString(string $markdown): self
    {
        return new self(str_replace("\r\n", "\n", $markdown));
    }

    /** @return list<string> the `[TODO: …]` placeholders still in the text */
    public function todos(): array
    {
        preg_match_all('/\[TODO:?[^\]]*\]/u', $this->markdown, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * Structure problems for a CV written in `$language`, as translated, user-facing messages.
     *
     * @return list<string>
     */
    public function warnings(Language $language): array
    {
        $lines = explode("\n", $this->markdown);
        $warnings = [];
        $headings = $language->sectionHeadings();
        $allowed = array_map('mb_strtolower', $headings);
        $datedHeadings = array_map(static fn (string $key): string => mb_strtolower($headings[$key]), self::DATED_SECTIONS);
        $section = null;
        $nameHeadings = 0;
        $undated = [];
        $unknown = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^#\s+\S/u', $line) === 1) {
                $nameHeadings++;
            } elseif (preg_match('/^##\s+(.+?)\s*$/u', $line, $match) === 1) {
                $section = mb_strtolower($match[1]);

                if (! in_array($section, $allowed, true)) {
                    $unknown[] = $match[1];
                }
            } elseif (preg_match('/^###\s+(.+?)\s*$/u', $line, $match) === 1 && in_array($section, $datedHeadings, true)) {
                if (preg_match('/^\*[^*].*\*\s*$/u', $this->nextNonEmptyLine($lines, $index)) !== 1) {
                    $undated[] = $match[1];
                }
            }
        }

        if ($nameHeadings !== 1) {
            $warnings[] = __('The CV should start with exactly one “#” heading: your name.');
        }

        foreach ($unknown as $heading) {
            $warnings[] = __('“:heading” is not a standard section. Use: :sections.', ['heading' => $heading, 'sections' => implode(', ', $headings)]);
        }

        foreach ($undated as $entry) {
            $warnings[] = __('“:entry” needs its dates on an italic line right below it, like “:example”.', ['entry' => $entry, 'example' => '*'.$language->dateFormatExample().'*']);
        }

        if (preg_match('/^\s*\|.*\|\s*$/mu', $this->markdown) === 1) {
            $warnings[] = __('Tables don’t survive ATS parsing. Use bullet points instead.');
        }

        if (preg_match('/<\/?[a-z][^>]*>/iu', $this->markdown) === 1) {
            $warnings[] = __('Remove the HTML tags: the CV must be plain Markdown.');
        }

        if (preg_match('/!\[[^\]]*\]\([^)]*\)/u', $this->markdown) === 1) {
            $warnings[] = __('Remove the images: ATS parsers can’t read them.');
        }

        return $warnings;
    }

    /** @param list<string> $lines */
    private function nextNonEmptyLine(array $lines, int $index): string
    {
        for ($next = $index + 1; $next < count($lines); $next++) {
            if (trim($lines[$next]) !== '') {
                return trim($lines[$next]);
            }
        }

        return '';
    }
}
