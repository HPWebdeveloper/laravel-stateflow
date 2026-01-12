# Silent & Force Transitions

StateFlow provides special transition modes for specific use cases.

## Silent Transitions (No Events)

Perform a transition without dispatching any events. Useful for:

- Bulk operations where you don't want event handlers to fire
- Data migrations
- Background processes that shouldn't trigger notifications

```php
// Normal transition - fires StateTransitioning and StateTransitioned events
$order->transitionTo('processing');

// Silent transition - no events fired
$order->transitionToWithoutEvents('processing');
```

## Force Transitions (Skip Validation)

Perform a transition that bypasses all validation rules. Useful for:

- Admin overrides
- Data corrections
- System-level operations

```php
// Normal transition - validates if transition is allowed
$order->transitionTo('delivered');
// Throws exception if not allowed

// Force transition - skips validation
$order->forceTransitionTo('delivered');
// Succeeds even if current state doesn't normally allow this transition
```

## Fluent API

You can also use the fluent API for more control:

```php
$result = $order->transition()
    ->to(Shipped::class)
    ->reason('Admin override')
    ->silent()    // No events
    ->force()     // Skip validation
    ->execute();
```

## ⚠️ Use with Caution

Force transitions bypass business rules. Use them only when you understand the implications:

- History is still recorded (unless you also use silent mode)
- No validation callbacks will run
- State consistency is your responsibility
