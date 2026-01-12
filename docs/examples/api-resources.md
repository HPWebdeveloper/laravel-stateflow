# API Resources

StateFlow provides ready-to-use API resources for JSON responses.

## StateResource

```php
use Hpwebdeveloper\LaravelStateflow\Http\Resources\StateResource;

// Single state
return StateResource::make($order->state);

// Response:
{
    "name": "pending",
    "title": "Pending",
    "color": "yellow",
    "icon": "clock",
    "description": "Order is pending confirmation",
    "is_current": true,
    "can_transition_to": true,
    "allowed_transitions": ["processing", "cancelled"]
}
```

## In API Controllers

```php
public function show(Order $order)
{
    return [
        'order' => new OrderResource($order),
        'current_state' => StateResource::make($order->state),
        'available_transitions' => collect($order->getNextStates())
            ->map(fn ($class) => StateResource::make(new $class($order))),
    ];
}
```

## Full Order Response

```php
public function show(Order $order)
{
    return response()->json([
        'data' => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'state' => [
                'name' => $order->state->name(),
                'title' => $order->state->title(),
                'color' => $order->state->color(),
            ],
            'next_states' => collect($order->getNextStates())
                ->map(fn ($class) => [
                    'name' => $class::name(),
                    'title' => $class::title(),
                    'action_url' => route('api.orders.transition', [
                        'order' => $order,
                        'state' => $class::name(),
                    ]),
                ]),
            'history' => $order->stateHistory->map(fn ($h) => [
                'from' => $h->from_state,
                'to' => $h->to_state,
                'at' => $h->created_at->toIso8601String(),
            ]),
        ],
    ]);
}
```

## Mobile App Integration

The consistent JSON structure makes it easy to build mobile apps:

```json
{
  "state": {
    "name": "shipped",
    "title": "Shipped",
    "color": "purple",
    "icon": "truck"
  },
  "next_states": [
    {
      "name": "delivered",
      "title": "Mark as Delivered"
    }
  ]
}
```

> 📦 **See it in action:** [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo)
