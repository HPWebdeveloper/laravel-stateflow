# Automatic Next States

StateFlow automatically discovers which states are reachable from the current state, eliminating the need for manual state selection in controllers.

## Basic Usage

```php
// Get available transitions from current state
$order->getNextStates();
// Returns: [Processing::class, Cancelled::class]

// Check if any transitions are available
$order->hasNextStates();
// Returns: true

// Check specific transition
$order->canTransitionTo(Processing::class);
// Returns: true
```

## In Controllers

```php
public function show(Order $order)
{
    return Inertia::render('orders/show', [
        'order' => $order,
        'nextStates' => collect($order->getNextStates())->map(fn ($class) => [
            'name' => $class::name(),
            'title' => $class::title(),
            'color' => $class::color(),
        ]),
    ]);
}
```

## Benefits

- No need to manually track which transitions are valid
- Controllers stay simple and maintainable
- Views can dynamically render transition buttons
- Business logic stays in state configuration

> 📦 **See it in action:** [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo)
