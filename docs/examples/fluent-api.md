# Fluent Transition API

StateFlow provides a fluent API for building transitions with full control over the process.

## Basic Usage

```php
$result = $order->transition()
    ->to(Shipped::class)
    ->execute();

if ($result->succeeded()) {
    // Transition completed
}
```

## With Metadata

```php
$result = $order->transition()
    ->to(Shipped::class)
    ->reason('Order shipped via FedEx')
    ->metadata([
        'tracking_number' => 'FX123456789',
        'carrier' => 'FedEx',
        'estimated_delivery' => '2026-01-15',
    ])
    ->execute();
```

## Full Example

```php
$result = $order->transition()
    ->to(Shipped::class)
    ->reason('Shipped from warehouse')
    ->metadata(['tracking' => $trackingNumber])
    ->performer($currentUser)     // Specify who performs the transition
    ->execute();

if ($result->failed()) {
    return back()->with('error', $result->error);
}

return back()->with('success', 'Order shipped successfully!');
```

## Chaining Options

```php
$order->transition()
    ->to(Processing::class)
    ->reason('Bulk processing')
    ->silent()              // Don't fire events
    ->execute();
```

## Benefits

- Clean, readable code
- All transition options in one place
- Easy to extend with additional options
- IDE-friendly with autocompletion

> 📦 **See it in action:** [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo)
