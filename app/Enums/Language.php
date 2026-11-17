<?php

namespace App\Enums;

/**
 * The two supported languages: English for remote jobs, Brazilian Portuguese for local ones.
 * Drives both the documents' output language and the UI locale.
 */
enum Language: string
{
    case En = 'en';
    case PtBr = 'pt-BR';

    /** Native name, shown untranslated so people can always find their own language. */
    public function label(): string
    {
        return match ($this) {
            self::En => 'English',
            self::PtBr => 'Português (Brasil)',
        };
    }

    public function laravelLocale(): string
    {
        return match ($this) {
            self::En => 'en',
            self::PtBr => 'pt_BR',
        };
    }

    public function htmlLang(): string
    {
        return match ($this) {
            self::En => 'en',
            self::PtBr => 'pt-BR',
        };
    }

    public function promptName(): string
    {
        return match ($this) {
            self::En => 'English',
            self::PtBr => 'Brazilian Portuguese (pt-BR)',
        };
    }

    /** @return array{summary: string, skills: string, experience: string, projects: string, education: string, certifications: string} */
    public function sectionHeadings(): array
    {
        return match ($this) {
            self::En => [
                'summary' => 'Summary',
                'skills' => 'Skills',
                'experience' => 'Experience',
                'projects' => 'Projects',
                'education' => 'Education',
                'certifications' => 'Certifications',
            ],
            self::PtBr => [
                'summary' => 'Resumo',
                'skills' => 'Competências',
                'experience' => 'Experiência',
                'projects' => 'Projetos',
                'education' => 'Formação',
                'certifications' => 'Certificações',
            ],
        };
    }

    public function dateFormatExample(): string
    {
        return match ($this) {
            self::En => 'Mar 2022 – Present',
            self::PtBr => 'mar 2022 – atual',
        };
    }

    public function presentWord(): string
    {
        return match ($this) {
            self::En => 'Present',
            self::PtBr => 'atual',
        };
    }

    public function remoteWord(): string
    {
        return match ($this) {
            self::En => 'Remote',
            self::PtBr => 'Remoto',
        };
    }

    public function coverLetterFilenameSuffix(): string
    {
        return match ($this) {
            self::En => 'Cover-Letter',
            self::PtBr => 'Carta-de-Apresentacao',
        };
    }

    public static function fromLaravelLocale(string $locale): self
    {
        foreach (self::cases() as $case) {
            if ($case->laravelLocale() === $locale) {
                return $case;
            }
        }

        return self::En;
    }

    /** Picks the first supported language in an Accept-Language header, by quality; English when none match. */
    public static function fromAcceptLanguage(?string $header): self
    {
        $ranked = collect(explode(',', (string) $header))
            ->map(static function (string $part): array {
                $pieces = explode(';', trim($part));
                $quality = 1.0;

                foreach (array_slice($pieces, 1) as $parameter) {
                    if (str_starts_with(trim($parameter), 'q=')) {
                        $quality = (float) substr(trim($parameter), 2);
                    }
                }

                return ['tag' => strtolower(trim($pieces[0])), 'quality' => $quality];
            })
            ->filter(static fn (array $entry): bool => $entry['tag'] !== '' && $entry['quality'] > 0)
            ->sortByDesc('quality');

        foreach ($ranked as $entry) {
            if ($entry['tag'] === 'pt' || str_starts_with($entry['tag'], 'pt-')) {
                return self::PtBr;
            }

            if ($entry['tag'] === 'en' || str_starts_with($entry['tag'], 'en-')) {
                return self::En;
            }
        }

        return self::En;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
