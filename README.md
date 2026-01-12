# Laravel StateFlow

[![CI](https://img.shields.io/github/actions/workflow/status/HPWebdeveloper/laravel-stateflow/ci.yml?branch=main&label=tests&style=flat-square)](https://github.com/HPWebdeveloper/laravel-stateflow/actions/workflows/ci.yml)
[![Code Style](https://img.shields.io/github/actions/workflow/status/HPWebdeveloper/laravel-stateflow/code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/HPWebdeveloper/laravel-stateflow/actions/workflows/code-style.yml)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/HPWebdeveloper/laravel-stateflow/static-analysis.yml?branch=main&label=static%20analysis&style=flat-square)](https://github.com/HPWebdeveloper/laravel-stateflow/actions/workflows/static-analysis.yml)

A modern, enterprise-ready state machine implementation for Laravel Eloquent models.

Laravel StateFlow is inspired by [Spatie Laravel Model States](https://github.com/spatie/laravel-model-states) and built independently from the ground up. It combines the [state pattern](https://en.wikipedia.org/wiki/State_pattern) with [state machines](https://www.youtube.com/watch?v=N12L5D78MAA) to deliver enterprise-ready features: automatic state class discovery, automatic transition discovery, permissions, UI metadata, history tracking, and API resources.

> 📦 **Demo Application:** See Laravel StateFlow in action with a complete order management demo at [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo).

## 📚 Table of Contents

- [Introduction](#introduction)
- [Why Laravel StateFlow?](#why-laravel-stateflow)
  - [The Problem with Manual Transitions](#the-problem-with-manual-transitions)
  - [StateFlow's Solution](#laravel-stateflows-solution-centralized-state-topology)
  - [Concrete Examples](#concrete-examples)
  - [Key Innovations](#key-innovations)
- [Installation](#installation)
- [Preparation Steps](#preparation-steps)
- [Transitions](#transitions)
- [Permissions](#permissions)
- [History Tracking](#history-tracking)
- [API Resources](#api-resources)
- [Query Scopes](#query-scopes)
- [Validation Rules](#validation-rules)
- [Events](#events)
- [Artisan Commands](#artisan-commands)
- [Configuration Reference](#configuration-reference)
- [Common Patterns](#common-patterns)
- [Version Compatibility](#version-compatibility)
- [License](#license)

---

## Introduction

This package adds state support to your Eloquent models. It lets you represent each state as a separate class, handles serialization to the database behind the scenes, and provides a clean API for state transitions with full authorization and audit capabilities.

**Example:** Imagine an `Order` model with states: `Pending`, `Processing`, `Shipped`, `Delivered`, and `Cancelled`. Each state can have its own color for UI, permitted roles for authorization, and the transitions between them are explicit and validated.

```php
// Check the current state
$order->state->name();        // 'pending'
$order->state->color();       // 'yellow'

// Get available transitions for the current user
$order->getNextStates();      // [Processing::class, Cancelled::class]

// Perform a transition with full audit trail
$order->transitionTo(Processing::class, reason: 'Order confirmed by warehouse');
```

## Why Laravel StateFlow?

### The Problem with Manual Transitions

Traditional state machine packages require you to manually decide the next state in every controller:

```php
// Manual approach - YOU must decide the target state
$order->state->transitionTo(Processing::class);
```

### ❌ This creates several pain points:

- In every controller, you must **manually select** the target state
- If there are multiple choices, you need **conditional statements**
- Transition logic becomes **hidden** in complex conditional statements across layers of code in actions/controllers
- Allowed transitions are **not centralized** — scattered throughout the codebase
- **Difficult to audit** which transitions are possible from any given state
- Not maintainable in large projects with complex state flows
- Views need logic to determine which transition buttons to show
- **Hard to test** all possible state transition paths

### Laravel-StateFlow's Solution: Centralized State Topology

Laravel StateFlow solves this with **centralized workflow definition** — see your entire state machine at a glance:

```php
// 📋 app/Enums/BookingWorkflow.php — Complete topology in ONE place!
enum BookingWorkflow: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function canTransitionTo(): array
    {
        return match ($this) {
            self::Draft     => [Confirmed::class],
            self::Confirmed => [Paid::class, Cancelled::class],
            self::Paid      => [Fulfilled::class, Cancelled::class],
            self::Fulfilled, self::Cancelled => [], // Final states
        };
    }
}
```

```php
// 🎯 app/Models/Booking.php — Clean, 3-line configuration!
class Booking extends Model implements HasStatesContract
{
    use HasStates;

    public static function registerStates(): void
    {
        static::addState('state', StateConfig::make(BookingState::class)
            ->default(Draft::class)
            ->registerStates(BookingWorkflow::stateClasses())
            ->allowTransitionsFromArray(BookingWorkflow::transitions())
        );
    }
}
```

> 💡 **See it live:** [BookingWorkflow.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Enums/BookingWorkflow.php) ・ [Booking.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Booking.php) ・ [Docs](docs/examples/enum-usage-states-transitions.md)

### Concrete Examples

**1. Automatic Next State Discovery**

```php
// Get available transitions from current state
$order->getNextStates();           // [Processing::class, Cancelled::class]
$order->hasNextStates();           // true
```

**2. Rich State Metadata**

```php
class Pending extends OrderState
{
    public const NAME = 'pending';

    public static function color(): string { return 'yellow'; }
    public static function title(): string { return 'Pending'; }
    public static function icon(): string { return 'clock'; }
}
```

**3. Declarative Transitions with Attributes**

```php
#[AllowTransition(to: Processing::class)]
#[AllowTransition(to: Cancelled::class)]
class Pending extends OrderState {}
```

**4. Multiple Permission Approaches**

```php
// Using attributes
#[StatePermission(roles: ['admin', 'warehouse'])]

// Using constants
public const PERMITTED_ROLES = ['admin', 'warehouse'];

// Using Laravel Policies
// Automatically checks OrderPolicy::transitionToProcessing()
```

**5. Clean Eloquent Integration**

```php
// StateFlow provides automatic casting
$order->state;                    // State instance
$order->state->name();            // 'pending'
$order->state->color();           // 'yellow'
$order->getNextStates();          // Auto-discovered next states
```

### Key Innovations

Laravel StateFlow provides enterprise features like automatic state discovery, rich UI metadata, built-in permissions, complete audit trails, and seamless Eloquent integration:

| Feature                          | ✓   | Description                                           | Example                                                                                                                                                                               |
| -------------------------------- | --- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Generate enum from states**    | ✅  | Create workflow enum from existing state classes      | `php artisan stateflow:sync-enum` ・ [Docs](docs/examples/enum-usage-states-transitions.md)                                                                                           |
| **Automatic next states**        | ✅  | Discover available transitions from current state     | [OrderController.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Http/Controllers/OrderController.php) ・ [Docs](docs/examples/automatic-next-states.md)  |
| **UI metadata**                  | ✅  | Colors, icons, titles for frontend integration        | [Pending.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/States/Order/Pending.php) ・ [Docs](docs/examples/ui-metadata.md)                                |
| **Eloquent integration**         | ✅  | Cast-based approach with clean, Laravel-native syntax | [Order.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Order.php)                                                                                  |
| **Role-based permissions**       | ✅  | Control transitions by user roles                     | [Processing.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/States/Order/Processing.php) ・ [Docs](docs/examples/permissions.md)                          |
| **Policy-based permissions**     | ✅  | Use Laravel policies for transition authorization     | [OrderPolicy.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Policies/OrderPolicy.php) ・ [Docs](docs/examples/permissions.md)                            |
| **State history & audit**        | ✅  | Complete transition history with performer tracking   | [OrderController.php#show](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Http/Controllers/OrderController.php#L66) ・ [Docs](docs/examples/state-history.md) |
| **API Resources**                | ✅  | Ready-to-use JSON responses for states                | [Docs](docs/examples/api-resources.md)                                                                                                                                                |
| **Advanced query scopes**        | ✅  | `orderByState`, `countByState`, `averageTimeInState`  | [Docs](docs/examples/query-scopes.md)                                                                                                                                                 |
| **Silent transitions**           | ✅  | Transition without firing events                      | [Docs](docs/examples/silent-force-transitions.md)                                                                                                                                     |
| **Force transitions**            | ✅  | Bypass validation for admin overrides                 | [Docs](docs/examples/silent-force-transitions.md)                                                                                                                                     |
| **Fluent transition API**        | ✅  | Clean, chainable API for transitions                  | [Docs](docs/examples/fluent-api.md)                                                                                                                                                   |
| **Centralized enum transitions** | ✅  | Define state topology in a single enum for clarity    | [BookingWorkflow.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Enums/BookingWorkflow.php) ・ [Docs](docs/examples/enum-usage-states-transitions.md)     |

## Installation

```bash
composer require hpwebdeveloper/laravel-stateflow
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="laravel-stateflow-config"
```

For history tracking, publish and run migrations:

```bash
php artisan vendor:publish --tag="laravel-stateflow-migrations"
php artisan migrate
```

---

## Preparation in 4 simple Steps

StateFlow supports **two approaches** for defining your state machine:

| Approach        | Best For                              | Transitions Defined In |
| --------------- | ------------------------------------- | ---------------------- |
| **Traditional** | Self-contained states, IDE navigation | State classes or model |
| **Hybrid Enum** | Centralized workflow visualization    | Single enum file       |

Both approaches are demonstrated in the [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo): **Orders** (traditional) and **Bookings** (enum).

### 1. Add State Column to Your Model

Add a `state` column to the model that will have state transitions. For example, if you have an `Order` model:

```php
// In a migration file
Schema::table('orders', function (Blueprint $table) {
    $table->string('state')->default('pending');
});
```

> **Note:** Replace `orders` with your table name (e.g., `posts`, `invoices`, `tickets`).

### 2. Create State Classes

Generate all state classes at once using the `--states` option:

```bash
php artisan make:state OrderState --states=Pending,Processing,Shipped,Delivered,Cancelled
```

This single command creates the base class and all extending state classes:

```
app/States/
  ├── OrderState.php      # Abstract base class
  ├── Pending.php
  ├── Processing.php
  ├── Shipped.php
  ├── Delivered.php
  └── Cancelled.php
```

**Alternative:** You can also create states individually:

```bash
php artisan make:state OrderState --base
php artisan make:state Pending --extends=OrderState
php artisan make:state Processing --extends=OrderState
# ... and so on
```

> 💡 **See the demo:** The [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo) uses this structure — see [States/Order/](https://github.com/HPWebdeveloper/laravel-stateflow-demo/tree/main/app/States/Order) for a complete example.

> ⚠️ **Important:** Keep all state classes in the **same directory** as their base state class. When adding new states later, use the full namespace:
>
> ```bash
> php artisan make:state Processing --extends=App\\States\\Booking\\BookingState
> ```
>
> The `stateflow:sync-enum` command only discovers states in the same directory as the base class.

### 3. Configure States

#### 3.1 Traditional Approach — State Classes with Metadata

StateFlow supports multiple approaches for defining state metadata: **Methods**, **Attributes**, and **Constants**. The demo uses a **combined approach** — attributes for static metadata (`title`, `description`) and methods for dynamic values (`color()`, `icon()`).

```php
// app/States/Order/Pending.php
use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\AllowTransition;

#[DefaultState]
#[StateMetadata(title: 'Pending', description: 'Order is pending confirmation')]
#[AllowTransition(to: Processing::class)]
#[AllowTransition(to: Cancelled::class)]
class Pending extends OrderState
{
    public const NAME = 'pending';

    public static function color(): string { return 'yellow'; }
    public static function icon(): string { return 'clock'; }
}
```

> 💡 **See the demo:** [Pending.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/States/Order/Pending.php) and [States/Order/](https://github.com/HPWebdeveloper/laravel-stateflow-demo/tree/main/app/States/Order)

#### 3.2 Hybrid Enum Approach — Centralized Workflow Topology

For teams who prefer seeing the **entire workflow at a glance**, use an enum to define the transition topology. State classes still handle behavior (colors, icons, metadata).

```bash
# Create states with enum scaffold
php artisan make:state BookingState --states=Draft,Confirmed,Paid,Fulfilled,Cancelled,Expired --transitions=enum
```

**The Enum — Shows all transitions in one place:**

```php
// app/Enums/BookingWorkflow.php
enum BookingWorkflow: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * 📋 Complete workflow topology at a glance!
     */
    public function canTransitionTo(): array
    {
        return match ($this) {
            self::Draft     => [Confirmed::class, Expired::class],
            self::Confirmed => [Paid::class, Cancelled::class, Expired::class],
            self::Paid      => [Fulfilled::class, Cancelled::class],
            // Final states — no transitions
            self::Fulfilled, self::Cancelled, self::Expired => [],
        };
    }

    public function stateClass(): string { /* maps to state class */ }
    public static function stateClasses(): array { /* all state classes */ }
    public static function transitions(): array { /* for StateConfig */ }
}
```

**State classes remain simple (behavior only, no transitions):**

```php
// app/States/Booking/Draft.php
#[DefaultState]
#[StateMetadata(title: 'Draft', description: 'Booking in draft')]
class Draft extends BookingState
{
    public const NAME = 'draft';
    public static function color(): string { return 'gray'; }
}
```

> 💡 **See the demo:** [BookingWorkflow.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Enums/BookingWorkflow.php) and [States/Booking/](https://github.com/HPWebdeveloper/laravel-stateflow-demo/tree/main/app/States/Booking)

> 📚 **Learn more:** See [Defining States and Transitions](docs/examples/enum-usage-states-transitions.md) for detailed comparison of all approaches.

### 4. Add to Model

#### 4.1 Traditional Approach — Explicit Transitions

```php
use Hpwebdeveloper\LaravelStateflow\HasStates;
use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Hpwebdeveloper\LaravelStateflow\StateConfig;

class Order extends Model implements HasStatesContract
{
    use HasStates;

    public static function registerStates(): void
    {
        static::addState('state', StateConfig::make(OrderState::class)
            ->default(Pending::class)
            ->registerStates([
                Pending::class,
                Processing::class,
                Shipped::class,
                Delivered::class,
                Cancelled::class,
            ])
            ->allowTransition(Pending::class, Processing::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Processing::class, Shipped::class)
            ->allowTransition(Processing::class, Cancelled::class)
            ->allowTransition(Shipped::class, Delivered::class)
        );
    }
}
```

> 💡 **See the demo:** [Order.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Order.php)

#### 4.2 Hybrid Enum Approach — Clean & Elegant ✨

With the enum approach, your model becomes remarkably clean:

```php
use App\Enums\BookingWorkflow;
use App\States\Booking\{BookingState, Draft};

class Booking extends Model implements HasStatesContract
{
    use HasStates;

    public static function registerStates(): void
    {
        static::addState('state', StateConfig::make(BookingState::class)
            ->default(Draft::class)
            ->registerStates(BookingWorkflow::stateClasses())
            ->allowTransitionsFromArray(BookingWorkflow::transitions())
        );
    }
}
```

**Benefits of the enum approach:**

- ✅ **3 lines** instead of 10+ for state configuration
- ✅ All transitions visible in **one file** (the enum)
- ✅ Easy to generate **workflow diagrams**
- ✅ Share workflows across **multiple models**

> 💡 **See the demo:** [Booking.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Booking.php)

> 📚 **Learn more:** See [Defining States and Transitions](docs/examples/enum-usage-states-transitions.md) for all approaches.

---

### Optional: Generate Enum & History Tracking

**Generate enum from existing state classes:**

```bash
php artisan stateflow:sync-enum App\\States\\Order\\OrderState
# Creates App\Enums\OrderStateStatus with all discovered states!
```

> **Naming Convention:** By default, the command creates `{BaseStateClass}Status` (e.g., `OrderState` → `OrderStateStatus`). Use `--enum=App\Enums\YourCustomName` to specify a different name.

> ⚠️ **Directory Requirement:** The sync command only discovers state classes in the **same directory** as the base state class. If you add new states later, ensure they are in the correct directory (e.g., `app/States/Order/` for `OrderState`).

**Enable history tracking:** Add `->recordHistory()` to your `StateConfig` and use the `HasStateHistory` trait. See [History Tracking](#history-tracking).

---

## How to use it

### Basic Usage

```php
$order = Order::create(['customer_name' => 'John Doe']);

// Check current state
$order->state;                              // Pending instance
$order->state->name();                      // 'pending'
$order->isInState('pending');               // true

// Check allowed transitions
$order->canTransitionTo('processing');      // true
$order->canTransitionTo('shipped');         // false (must process first)
$order->getNextStates();                    // [Processing::class, Cancelled::class]

// Transition
$result = $order->transitionTo('processing');
$result->succeeded();                       // true
$order->state->name();                      // 'processing'
```

> 💡 **Full Example:** See the [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo) for a complete implementation — [Order.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Order.php) (model), [OrderController.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Http/Controllers/OrderController.php) (controller), and [States/Order/](https://github.com/HPWebdeveloper/laravel-stateflow-demo/tree/main/app/States/Order) (state classes).

### Serializing States

States are stored in the database using their `NAME` constant value:

```php
// Creating with a state class
$order = Order::create([
    'state' => Processing::class,  // Stored as 'processing' in DB
]);

// The package handles serialization automatically
$order->state;              // Returns Processing instance
$order->state->name();      // 'processing'
```

> **Tip:** You can use class names (e.g., `Processing::class`) throughout your code - the package handles mapping to/from database values.

### Listing Registered States

```php
// Get all states for the model (grouped by field)
Order::getStates();
// Returns: ['state' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled']]

// Get states for a specific field
Order::getStatesFor('state');
// Returns: ['pending', 'processing', 'shipped', 'delivered', 'cancelled']

// Get default states
Order::getDefaultStates();
// Returns: ['state' => 'pending']

// Get default for specific field
Order::getDefaultStateFor('state');
// Returns: 'pending'
```

### Retrieving Transitionable States

```php
// Get state classes you can transition to from current state
$order->getNextStates();
// Returns: [Processing::class, Cancelled::class] (when in pending state)

// Count available transitions
count($order->getNextStates());
// Returns: 2

// Check if any transitions available
$order->hasNextStates();
// Returns: true

// Check specific transition
$order->canTransitionTo(Processing::class);
// Returns: true
```

### Using States in Blade Templates

```blade
{{-- Display current state with color badge --}}
<span class="badge" style="background-color: {{ $order->state->color() }}">
    {{ $order->state->title() }}
</span>

{{-- Show available transition buttons --}}
@foreach($order->getNextStates() as $nextStateClass)
    <form action="{{ route('orders.transition', $order) }}" method="POST">
        @csrf
        <input type="hidden" name="state" value="{{ $nextStateClass::name() }}">
        <button type="submit" class="btn btn-{{ $nextStateClass::color() }}">
            <i class="{{ $nextStateClass::icon() }}"></i>
            {{ $nextStateClass::title() }}
        </button>
    </form>
@endforeach
```

---

## Transitions

### Basic Transition

```php
$result = $order->transitionTo('processing');

if ($result->succeeded()) {
    // Transition completed
}

if ($result->failed()) {
    echo $result->error;  // Error message
}
```

### With Metadata

```php
$result = $order->transitionTo(
    state: Shipped::class,
    reason: 'Shipped via FedEx',
    metadata: ['tracking_number' => 'FX123456789']
);
```

### Fluent API

```php
$result = $order->transition()
    ->to(Shipped::class)
    ->reason('Order shipped')
    ->metadata(['carrier' => 'FedEx'])
    ->execute();
```

### Silent Transition (No Events)

```php
$order->transitionToWithoutEvents('processing');
```

### Force Transition (Skip Validation)

```php
$order->forceTransitionTo('delivered');
```

---

## Permissions

StateFlow provides flexible permission control through role-based and policy-based authorization. Control who can perform state transitions based on user roles, ownership, or complex business logic.

**📖 [Complete Permissions Documentation](docs/examples/permissions.md)**

Quick example:

```php
// Role-based: Define permitted roles in state class
#[StatePermission(roles: ['admin', 'warehouse'])]
class Shipped extends OrderState {}

// Policy-based: Complex authorization logic
class OrderPolicy {
    public function transitionToShipped(User $user, Order $order): bool {
        return $user->hasRole('warehouse') && $order->isPaid();
    }
}

// Check permissions
$order->userCanTransitionTo($user, 'shipped');
```

---

## History Tracking

### Enable History

```php
use Hpwebdeveloper\LaravelStateflow\Concerns\HasStateHistory;

class Order extends Model implements HasStatesContract
{
    use HasStates, HasStateHistory;
}
```

### Query History

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

### History Record

```php
$history = $order->stateHistory->first();

$history->from_state;    // 'pending'
$history->to_state;      // 'processing'
$history->reason;        // 'Order confirmed by warehouse'
$history->performer;     // User model
$history->metadata;      // ['key' => 'value']
$history->transitioned_at;
```

> 💡 **See it in action:** The demo shows complete history tracking with a timeline UI — see [OrderController.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Http/Controllers/OrderController.php#L66-L85) for how history is queried and formatted.

---

## API Resources

### State Resource

```php
use Hpwebdeveloper\LaravelStateflow\Http\Resources\StateResource;

// Single state
return StateResource::make($order->state);

// All available states
return StateResource::collection($order->getAvailableStates());

// Response format
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

### In Controller

```php
public function show(Order $order)
{
    return [
        'order' => $order,
        'current_state' => StateResource::make($order->state),
        'available_states' => StateResource::collection(
            $order->getAvailableStates()
        ),
    ];
}
```

---

## Query Scopes

```php
// By state
Order::whereState('shipped')->get();
Order::whereNotState('pending')->get();
Order::whereStateIn(['pending', 'processing'])->get();

// By transition capability
Order::whereCanTransitionTo('shipped')->get();

// History-based
Order::whereWasEverInState('processing')->get();
Order::whereNeverInState('cancelled')->get();
```

---

## Validation Rules

```php
use Hpwebdeveloper\LaravelStateflow\Validation\StateRule;
use Hpwebdeveloper\LaravelStateflow\Validation\TransitionRule;

// Validate state value
$request->validate([
    'state' => ['required', StateRule::make(OrderState::class)],
]);

// Validate transition is allowed
$request->validate([
    'new_state' => ['required', TransitionRule::make($order)],
]);
```

---

## Events

```php
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;

// In EventServiceProvider
protected $listen = [
    StateTransitioning::class => [
        ValidateInventory::class,
    ],
    StateTransitioned::class => [
        SendOrderNotification::class,
        UpdateInventory::class,
    ],
    TransitionFailed::class => [
        LogFailure::class,
    ],
];
```

### Event Properties

```php
// StateTransitioned event
$event->model;       // The model
$event->field;       // 'state'
$event->fromState;   // 'pending'
$event->toState;     // 'processing'
$event->performer;   // User who performed transition
$event->reason;      // Reason string
$event->metadata;    // Additional data
```

---

## Artisan Commands

```bash
# Generate state class
php artisan make:state Pending --extends=OrderState

# Generate base state class
php artisan make:state OrderState --base

# Generate all states at once
php artisan make:state OrderState --states=Pending,Processing,Shipped,Delivered

# Generate enum from existing state classes
php artisan stateflow:sync-enum App\\States\\Order\\OrderState
# Or with custom enum name:
php artisan stateflow:sync-enum App\\States\\Order\\OrderState --enum=App\\Enums\\OrderWorkflow

# Generate transition class
php artisan make:transition ShipOrder

# List all states for a model
php artisan stateflow:list "App\Models\Order"

# Audit state configurations
php artisan stateflow:audit
```

> 🌟 **Key Feature:** The `stateflow:sync-enum` command scans your state directory and generates a workflow enum with all discovered states. This creates an enum with `stateClasses()`, `canTransitionTo()`, and `transitions()` methods ready for use in your model's `registerStates()` method.

---

## Configuration Reference

```php
// config/laravel-stateflow.php
return [
    // Default database column for state
    'default_state_field' => 'state',

    // Directory for generated state classes
    'states_directory' => 'States',

    // History tracking
    'history' => [
        'enabled' => true,
        'table' => 'state_histories',
        'prune_after_days' => null,
    ],

    // Permission system
    'permissions' => [
        'enabled' => true,
        'role_based' => true,
        'policy_based' => false,
        'throw_on_unauthorized' => true,
    ],

    // Event dispatching
    'events' => [
        'enabled' => true,
    ],
];
```

---

## Common Patterns

### Order Workflow

```php
class Order extends Model implements HasStatesContract
{
    use HasStates;

    public static function registerStates(): void
    {
        static::addState('state', StateConfig::make(OrderState::class)
            ->default(Pending::class)
            ->allowTransition(Pending::class, Processing::class)
            ->allowTransition(Processing::class, Shipped::class)
            ->allowTransition(Shipped::class, Delivered::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Processing::class, Cancelled::class)
        );
    }
}
```

> 💡 **See this in action:** The [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo) demonstrates this workflow — see [Order.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Order.php) for the model and [OrderController.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Http/Controllers/OrderController.php) for the controller implementation.

### Multiple State Fields

```php
public static function registerStates(): void
{
    // Order status (main workflow)
    static::addState('state', StateConfig::make(OrderState::class)
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->allowTransition(Processing::class, Shipped::class)
        ->allowTransition(Shipped::class, Delivered::class)
        ->allowTransition(Pending::class, Cancelled::class)
        ->allowTransition(Processing::class, Cancelled::class)
    );

    // Payment status (separate workflow)
    static::addState('payment_status', StateConfig::make(PaymentStatus::class)
        ->default(Unpaid::class)
        ->allowTransition(Unpaid::class, Paid::class)
        ->allowTransition(Paid::class, Refunded::class)
    );
}

// Usage
$order->transitionTo(Processing::class, field: 'state');
$order->transitionTo(Paid::class, field: 'payment_status');
```

### Custom Transition Logic

```bash
php artisan make:transition ShipOrder
```

```php
// app/Transitions/ShipOrder.php
use Hpwebdeveloper\LaravelStateflow\Transition;

class ShipOrder extends Transition
{
    public function handle(): void
    {
        $this->model->shipped_at = now();
        $this->model->save();

        // Send notification, update inventory, etc.
    }

    public function canTransition(): bool
    {
        return $this->model->shipping_address !== null
            && $this->model->total > 0;
    }
}
```

Register in config:

```php
->allowTransition(Processing::class, Shipped::class, ShipOrder::class)
```

### Dependency Injection in Transitions

The `handle()` method supports dependency injection via Laravel's container:

```php
use App\Services\NotificationService;
use App\Services\InventoryService;

class ShipOrder extends Transition
{
    public function handle(
        NotificationService $notifications,
        InventoryService $inventory
    ): void {
        $this->model->shipped_at = now();
        $this->model->save();

        // Services are automatically resolved from the container
        $notifications->sendShippedNotification($this->model);
        $inventory->decrementStock($this->model->items);
    }
}
```

This allows for clean separation of concerns and easier testing through dependency mocking.

---

## Version Compatibility

| Package Version | Laravel Versions | PHP Versions | Status         |
| --------------- | ---------------- | ------------ | -------------- |
| 1.x             | 12.x             | 8.3+         | Active support |

**Note:** The package is currently built for Laravel 12 with PHP 8.3+. Support for earlier Laravel versions may be added in future releases.

---

## License

The MIT License (MIT). See [License File](LICENSE.md) for more information.
