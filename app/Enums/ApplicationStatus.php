<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Ready => __('Ready'),
            self::Applied => __('Applied'),
            self::Interviewing => __('Interviewing'),
            self::Offer => __('Offer'),
            self::Rejected => __('Rejected'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    /** A Tailwind-friendly color name; the UI maps it to badge classes. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ready => 'blue',
            self::Applied => 'indigo',
            self::Interviewing => 'amber',
            self::Offer => 'green',
            self::Rejected => 'red',
            self::Withdrawn => 'slate',
        };
    }

    /** @return list<array{value: string, label: string, color: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => ['value' => $case->value, 'label' => $case->label(), 'color' => $case->color()], self::cases());
    }
}
