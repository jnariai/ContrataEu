<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/** How many of the job's top keywords the tailored CV contains. */
final readonly class KeywordCoverage implements JsonSerializable
{
    /** @param list<string> $missing */
    private function __construct(
        public int $matched,
        public int $total,
        public array $missing,
    ) {}

    /** @param list<string> $missing */
    public static function of(int $matched, int $total, array $missing): self
    {
        if ($total < 0 || $matched < 0 || $matched > $total) {
            throw new InvalidArgumentException("Invalid keyword coverage [{$matched}/{$total}].");
        }

        return new self($matched, $total, $missing);
    }

    /** Whole percentage, 0 when there are no keywords. */
    public function percentage(): int
    {
        return $this->total === 0 ? 0 : (int) round($this->matched / $this->total * 100);
    }

    /** @return array{matched: int, total: int, missing: list<string>, percentage: int} */
    public function jsonSerialize(): array
    {
        return ['matched' => $this->matched, 'total' => $this->total, 'missing' => $this->missing, 'percentage' => $this->percentage()];
    }
}
