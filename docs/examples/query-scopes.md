# Advanced Query Scopes

StateFlow provides powerful query scopes for filtering and analyzing models by state.

## Basic Scopes

```php
// Filter by state
Order::whereState('shipped')->get();
Order::whereNotState('cancelled')->get();
Order::whereStateIn(['pending', 'processing'])->get();

// Filter by transition capability
Order::whereCanTransitionTo('shipped')->get();
```

## History-Based Scopes

```php
// Orders that were ever in a specific state
Order::whereWasEverInState('processing')->get();

// Orders that were never in a specific state
Order::whereNeverInState('cancelled')->get();
```

## State Statistics

```php
// Count orders by state
Order::countByState();
// Returns: ['pending' => 10, 'processing' => 5, 'shipped' => 20, ...]

// Average time spent in each state
Order::averageTimeInState();
// Returns: ['pending' => '2 hours', 'processing' => '1 day', ...]
```

## Ordering

```php
// Order by state (custom order defined in config)
Order::orderByState()->get();

// With transition count
Order::withTransitionCount()->get();
// Each order will have $order->transition_count
```

## Complex Queries

```php
// Pending orders older than 24 hours
Order::whereState('pending')
    ->where('created_at', '<', now()->subDay())
    ->get();

// Orders stuck in processing
Order::whereState('processing')
    ->whereWasEverInState('pending')
    ->where('updated_at', '<', now()->subHours(6))
    ->get();
```

## Dashboard Statistics

```php
public function dashboard()
{
    return [
        'counts' => Order::countByState(),
        'pending_urgent' => Order::whereState('pending')
            ->where('created_at', '<', now()->subHours(2))
            ->count(),
        'shipped_today' => Order::whereState('shipped')
            ->whereDate('updated_at', today())
            ->count(),
    ];
}
```

> 📦 **See it in action:** [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo)
