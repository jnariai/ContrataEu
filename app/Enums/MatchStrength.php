<?php

namespace App\Enums;

/** How well the candidate's evidence meets one job requirement. Lives inside the report JSON, so it has no lookup table. */
enum MatchStrength: string
{
    case Strong = 'strong';
    case Partial = 'partial';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Strong => __('Strong'),
            self::Partial => __('Partial'),
            self::Missing => __('Missing'),
        };
    }
}
