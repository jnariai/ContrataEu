<?php

namespace App\Enums;

enum DocumentType: string
{
    case Cv = 'cv';
    case CoverLetter = 'cover_letter';
    case RecruiterMessage = 'recruiter_message';

    public function label(): string
    {
        return match ($this) {
            self::Cv => __('CV'),
            self::CoverLetter => __('Cover letter'),
            self::RecruiterMessage => __('Recruiter message'),
        };
    }

    /** The order documents are shown in: the CV first. */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Cv => 1,
            self::CoverLetter => 2,
            self::RecruiterMessage => 3,
        };
    }

    /** Only the CV must exist before generating; the others are optional inspiration. */
    public function isRequired(): bool
    {
        return match ($this) {
            self::Cv => true,
            self::CoverLetter, self::RecruiterMessage => false,
        };
    }

    public function hasPdf(): bool
    {
        return match ($this) {
            self::Cv, self::CoverLetter => true,
            self::RecruiterMessage => false,
        };
    }

    /** @return view-string|null */
    public function pdfView(): ?string
    {
        return match ($this) {
            self::Cv => 'pdf.cv',
            self::CoverLetter => 'pdf.cover-letter',
            self::RecruiterMessage => null,
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
