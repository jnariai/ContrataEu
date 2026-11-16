# Factories

- The default state is a **valid** model, the one a named constructor would produce. Factories are the one place that writes attributes directly.
- A named state per status (`->paid()`, `->shipped()`, `->cancelled()`) and per common relationship (`->for($customer)`, `->withLines(3)`), so tests read like the domain.
- Seeders use factories; there are no production data seeders.
```php
/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'address_id' => Address::factory(),
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'total' => Money::of(10_00, Currency::EUR),
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::Paid]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()->toImmutable()]);
    }
}
```

