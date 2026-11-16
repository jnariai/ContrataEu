# Output Data

```php
final readonly class OrderSummary
{
    public function __construct(
        public string $id,
        public OrderStatus $status,
        public string $statusLabel,
        public string $total,
        public string $placedAt,
        public bool $isCancellable,
        public CustomerSummary $customer,
    ) {}

    public static function fromModel(Order $order): self
    {
        return new self(
            id: $order->public_id,
            status: $order->status,
            statusLabel: $order->status->label(),
            total: $order->total->formatted(),
            placedAt: $order->created_at->toIso8601ZuluString(),
            isCancellable: $order->isCancellable(),
            customer: CustomerSummary::fromModel($order->customer),
        );
    }
}

final readonly class CustomerSummary
{
    public function __construct(public string $id, public string $name) {}

    public static function fromModel(User $user): self
    {
        return new self(id: $user->public_id, name: $user->name);
    }
}
```

## Lists and pagination
A list maps each model; a paginator uses `through()`, which keeps Laravel's paginator shape (links, meta).
```php
$orders = Order::openFor($customer)->with('customer')->get()->map(OrderSummary::fromModel(...))->all();

$orders = Order::openFor($customer)->with('customer')->paginate(20)->through(OrderSummary::fromModel(...));
```

