# More enum examples

## A case-only rule that isn't about state
```php
enum PaymentMethod: string
{
    case Card = 'card';
    case Pix = 'pix';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Card',
            self::Pix => 'Pix',
            self::BankTransfer => 'Bank transfer',
        };
    }

    public function acceptsCurrency(Currency $currency): bool
    {
        return match ($this) {
            self::Card => true,
            self::Pix => $currency === Currency::BRL,
            self::BankTransfer => in_array($currency, [Currency::BRL, Currency::EUR], true),
        };
    }
}
```

## Scopes take cases and resolve the lookup ids
Callers never see lookup ids.
```php
#[Scope]
protected function inStatus(Builder $query, OrderStatus ...$statuses): void
{
    $query->whereIn('status_id', array_map(static fn (OrderStatus $status): int => LookupCast::idOf('order_statuses', $status), $statuses));
}

#[Scope]
protected function unfinished(Builder $query): void
{
    $query->inStatus(...OrderStatus::unfinished());
}
```

## Validation
```php
public function rules(): array
{
    return [
        'status' => ['required', Rule::enum(OrderStatus::class)->except([OrderStatus::Cancelled])], // cancelling has its own endpoint
        'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
    ];
}
```

## Transition test
```php
it('allows only the defined transitions', function (OrderStatus $from, OrderStatus $to, bool $allowed): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending → paid' => [OrderStatus::Pending, OrderStatus::Paid, true],
    'paid → shipped' => [OrderStatus::Paid, OrderStatus::Shipped, true],
    'shipped → cancelled' => [OrderStatus::Shipped, OrderStatus::Cancelled, false],
    'cancelled → paid' => [OrderStatus::Cancelled, OrderStatus::Paid, false],
]);
```
