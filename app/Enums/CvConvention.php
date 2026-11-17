<?php

namespace App\Enums;

/**
 * The regional CV norms a document follows (personal data, length, location format, naming). Tied to the document
 * language: Portuguese documents are for Brazilian jobs and always follow Brazil; for English documents the
 * candidate picks one, International / EU by default.
 */
enum CvConvention: string
{
    case International = 'international';
    case UnitedStates = 'us';
    case UnitedKingdom = 'uk';
    case Brazil = 'brazil';

    public function label(): string
    {
        return match ($this) {
            self::International => __('International / EU'),
            self::UnitedStates => __('United States'),
            self::UnitedKingdom => __('United Kingdom'),
            self::Brazil => __('Brazil'),
        };
    }

    /** What this convention changes, for the candidate profile page. */
    public function description(): string
    {
        return match ($this) {
            self::International => __('Remote and European jobs. 1–2 pages, city and country, no photo or date of birth.'),
            self::UnitedStates => __('US jobs. A one-page résumé, city and state, never a photo, age or marital status.'),
            self::UnitedKingdom => __('UK jobs. Up to 2 pages, city only, no photo or date of birth, a short personal statement as the summary.'),
            self::Brazil => __('Brazilian jobs. 1–2 pages, city and state, LinkedIn link, no CPF/RG, age, marital status or photo.'),
        };
    }

    /** The app-owned prompt part with this convention's rules (resources/prompts/app/conventions). */
    public function promptPart(): string
    {
        return 'conventions/'.$this->value;
    }

    /** Portuguese documents always follow Brazil; English ones follow the candidate's choice. */
    public static function forDocument(Language $language, ?self $englishConvention): self
    {
        return match ($language) {
            Language::PtBr => self::Brazil,
            Language::En => $englishConvention ?? self::International,
        };
    }

    /** @return list<self> the conventions a candidate can pick for English documents */
    public static function forEnglish(): array
    {
        return [self::International, self::UnitedStates, self::UnitedKingdom];
    }

    /** @return list<array{value: string, label: string, description: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label(), 'description' => $case->description()],
            self::forEnglish(),
        );
    }
}
