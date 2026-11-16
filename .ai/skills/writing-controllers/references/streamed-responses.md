# Streamed responses (exports, SSE)

1. Use `response()->streamDownload()` (file exports), `response()->streamJson()` or `response()->eventStream()` (server-sent events).
2. The callback **only iterates and writes** what a model query (`lazy()` / `lazyById()` / `cursor()`) or an Action returns. It holds no business logic and writes no state. Filtering lives in scopes.
3. Set the content type and filename explicitly. The route is authorized like any other (`->can(...)` or a Policy in the controller's FormRequest).

```php
class OrderExportController extends Controller
{
    public function show(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['id', 'status', 'total', 'placed_at']);

            foreach (Order::openFor($request->user())->with('customer')->lazyById() as $order) {
                fputcsv($output, [$order->public_id, $order->status->value, $order->total->formatted(), $order->created_at->toIso8601ZuluString()]);
            }

            fclose($output);
        }, 'orders-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
```

