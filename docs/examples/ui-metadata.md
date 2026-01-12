# UI Metadata

StateFlow provides built-in support for UI-related metadata on states, making frontend integration seamless.

## Defining Metadata

```php
class Pending extends OrderState
{
    public const NAME = 'pending';
    public const TITLE = 'Pending';
    public const COLOR = 'yellow';
    public const ICON = 'clock';
    public const DESCRIPTION = 'Order is pending confirmation';
}
```

Or using attributes:

```php
#[StateMetadata(title: 'Pending', color: 'yellow', icon: 'clock')]
class Pending extends OrderState
{
    public const NAME = 'pending';
}
```

## Accessing Metadata

```php
$order->state->name();        // 'pending'
$order->state->title();       // 'Pending'
$order->state->color();       // 'yellow'
$order->state->icon();        // 'clock'
$order->state->description(); // 'Order is pending confirmation'
```

## Frontend Integration (React/Inertia)

```tsx
function StateBadge({ state }) {
  const colorClasses = {
    yellow: "bg-yellow-100 text-yellow-800",
    blue: "bg-blue-100 text-blue-800",
    green: "bg-green-100 text-green-800",
    red: "bg-red-100 text-red-800",
  };

  return (
    <span className={`px-2 py-1 rounded ${colorClasses[state.color]}`}>
      <Icon name={state.icon} />
      {state.title}
    </span>
  );
}
```

## Blade Templates

```blade
<span class="badge" style="background-color: {{ $order->state->color() }}">
    <i class="{{ $order->state->icon() }}"></i>
    {{ $order->state->title() }}
</span>
```

> 📦 **See it in action:** [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo)
