# Full model example: Order

```php
<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Casts\MoneyCast;
use App\Exceptions\OrderCannotBeCancelled;
use App\Exceptions\OrderCannotBeChanged;
use App\Exceptions\OrderCannotBePlaced;
use App\Casts\LookupCast;
use App\Models\Concerns\HasPublicId;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property int $address_id
 * @property int|null $cancelled_by_id
 * @property int $status_id
 * @property OrderStatus $status
 * @property int $payment_method_id
 * @property PaymentMethod $payment_method
 * @property Money $total
 * @property CarbonImmutable|null $deliver_on
 * @property string|null $note
 * @property CancellationReason|null $cancellation_reason
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $customer
 * @property-read Address $address
 * @property-read User|null $cancelledBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrderLine> $lines
 */
#[Fillable([
    'status',
    'payment_method',
    'total',
    'deliver_on',
    'note',
    'cancellation_reason',
    'cancelled_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;
    use HasPublicId;

    public const string PUBLIC_ID_PREFIX = 'ord_';

    public static function place(
        User $customer,
        Address $address,
        PaymentMethod $paymentMethod,
        Money $total,
        ?CarbonImmutable $deliverOn = null,
        ?string $note = null,
    ): self {
        if (! $address->belongsTo($customer)) {
            throw OrderCannotBePlaced::foreignAddress($address);
        }

        if (! $paymentMethod->acceptsCurrency($total->currency)) {
            throw OrderCannotBePlaced::unsupportedCurrency($paymentMethod, $total->currency);
        }

        $order = new self([
            'status' => OrderStatus::Pending,
            'payment_method' => $paymentMethod,
            'total' => $total,
            'deliver_on' => $deliverOn,
            'note' => $note,
        ]);
        $order->customer()->associate($customer);
        $order->address()->associate($address);
        $order->save();

        return $order;
    }

    public function addLine(Product $product, int $quantity): OrderLine
    {
        if ($this->status !== OrderStatus::Pending) {
            throw OrderCannotBeChanged::fromStatus($this->status);
        }

        if ($product->price->currency !== $this->total->currency) {
            throw OrderCannotBeChanged::currencyMismatch($product, $this->total->currency);
        }

        return OrderLine::add($this, $product, $quantity);
    }

    public function cancel(User $actor, CancellationReason $reason, CarbonImmutable $at): void
    {
        if (! $this->isCancellable()) {
            throw OrderCannotBeCancelled::fromStatus($this->status);
        }

        $this->cancelledBy()->associate($actor);
        $this->update([
            'status' => OrderStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_at' => $at,
        ]);
    }

    public function isCancellable(): bool
    {
        return $this->status->canTransitionTo(OrderStatus::Cancelled);
    }

    /** @return Builder<self> */
    public static function openFor(User $customer): Builder
    {
        return static::query()->whereBelongsTo($customer, 'customer')->unfinished()->latest('id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function inStatus(Builder $query, OrderStatus ...$statuses): void
    {
        $query->whereIn('status_id', array_map(static fn (OrderStatus $status): int => LookupCast::idOf('order_statuses', $status), $statuses));
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function unfinished(Builder $query): void
    {
        $query->inStatus(...OrderStatus::unfinished());
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Address, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => LookupCast::of(OrderStatus::class, 'order_statuses', 'status_id'),
            'payment_method' => LookupCast::of(PaymentMethod::class, 'payment_methods', 'payment_method_id'),
            'total' => MoneyCast::class,
            'deliver_on' => 'immutable_date',
            'cancellation_reason' => CancellationReason::class,
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
```
Usage from a controller or an Action:
```php
$order = Order::place($customer, $address, PaymentMethod::Card, Money::of(12_50, Currency::EUR));
$order->addLine($product, quantity: 2);
$order->cancel($actor, CancellationReason::ChangedMind, now()->toImmutable());

$open = Order::openFor($customer)->cursorPaginate(25);
```

