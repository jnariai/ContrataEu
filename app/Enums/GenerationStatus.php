<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Running => __('Generating'),
            self::Succeeded => __('Done'),
            self::Failed => __('Failed'),
        };
    }

    public function isFinished(): bool
    {
        return match ($this) {
            self::Queued, self::Running => false,
            self::Succeeded, self::Failed => true,
        };
    }

    /** @return list<self> */
    public static function unfinished(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => ! $case->isFinished()));
    }
}
