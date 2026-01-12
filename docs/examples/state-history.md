# State History & Audit Trail

StateFlow provides complete history tracking for all state transitions, including who performed them and why.

## Enable History

```php
use Hpwebdeveloper\LaravelStateflow\Concerns\HasStateHistory;

class Order extends Model implements HasStatesContract
{
    use HasStates, HasStateHistory;
}
```

## Transition with Context

```php
$order->transitionTo(
    state: Shipped::class,
    reason: 'Shipped via FedEx',
    metadata: ['tracking_number' => 'FX123456789']
);
```

## Query History

```php
// Get all history
$order->stateHistory;

// Get history for specific field
$order->stateHistoryFor('state');

// Get previous state
$order->previousState();

// Get initial state
$order->initialState();
```

## History Record Structure

```php
$history = $order->stateHistory->first();

$history->from_state;      // 'pending'
$history->to_state;        // 'processing'
$history->reason;          // 'Order confirmed by warehouse'
$history->performer;       // User model (who performed the transition)
$history->metadata;        // ['key' => 'value']
$history->transitioned_at; // Carbon instance
```

## Display History Timeline

```php
$history = $order->stateHistory()
    ->orderBy('created_at', 'desc')
    ->get()
    ->map(fn ($record) => [
        'from' => $record->from_state,
        'to' => $record->to_state,
        'reason' => $record->reason,
        'performer' => $record->performer?->name ?? 'System',
        'date' => $record->created_at->format('M d, Y H:i'),
    ]);
```

> 📦 **See it in action:** [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo) - includes a complete history timeline UI
