# DateRange

```php
<?php

namespace App\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

/** An inclusive range of calendar days. */
final readonly class DateRange implements JsonSerializable
{
    private function __construct(public CarbonImmutable $start, public CarbonImmutable $end) {}

    public static function between(CarbonImmutable $start, CarbonImmutable $end): self
    {
        if ($end->lt($start)) {
            throw new InvalidArgumentException("The range ends ({$end->toDateString()}) before it starts ({$start->toDateString()}).");
        }

        return new self($start->startOfDay(), $end->endOfDay());
    }

    public function contains(CarbonImmutable $moment): bool
    {
        return $moment->betweenIncluded($this->start, $this->end);
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end->startOfDay()) + 1;
    }

    public function equals(self $other): bool
    {
        return $this->start->equalTo($other->start) && $this->end->equalTo($other->end);
    }

    /** @return array{start: string, end: string} */
    public function jsonSerialize(): array
    {
        return ['start' => $this->start->toDateString(), 'end' => $this->end->toDateString()];
    }
}
```
A scope takes it as is: `#[Scope] protected function paidBetween(Builder $query, DateRange $range): void { $query->whereBetween('paid_at', [$range->start, $range->end]); }`.

