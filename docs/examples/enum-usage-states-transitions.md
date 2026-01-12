# Defining States and Transitions

StateFlow is flexible and supports multiple approaches for defining your state machine. This guide covers the different patterns so you can choose what works best for your team.

> 📦 **Live Examples:** All approaches shown here are demonstrated in the [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo) repository:
>
> - **Orders** — Uses explicit/attribute approach
> - **Bookings** — Uses hybrid enum approach

---

## Quick Overview

| Approach        | Transitions Defined In                  | Best For                              | Demo Example                                                                                                   |
| --------------- | --------------------------------------- | ------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| **Explicit**    | Model's `registerStates()`              | Full control, simple workflows        | [Order.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Order.php)           |
| **Attributes**  | State classes via `#[AllowTransition]`  | Self-contained states, IDE navigation | [Pending.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/States/Order/Pending.php) |
| **Hybrid Enum** | Enum for topology, classes for behavior | Centralized workflow visualization    | [Booking.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Booking.php)       |

---

## Approach 1: Explicit Transitions in Model

Define all states and transitions directly in your model's `registerStates()` method.

### Example: Order Model (from Demo)

```php
// app/Models/Order.php
use App\States\Order\{OrderState, Pending, Processing, Shipped, Delivered, Cancelled};

class Order extends Model implements HasStatesContract
{
    use HasStates, HasStateHistory;

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

### When to Use This Approach

✅ **Pros:**

- Everything in one place — model controls its own workflow
- Easy to understand at a glance
- No additional files needed beyond state classes

❌ **Cons:**

- State classes are listed twice (registration + transitions)
- Adding new states requires editing multiple places

> 📁 **See it in action:** [Order.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Order.php)

---

## Approach 2: Attribute-Based Transitions

Define transitions directly in state classes using the `#[AllowTransition]` attribute. Each state declares where it can transition to.

### Example: Order State Classes (from Demo)

```php
// app/States/Order/Pending.php
use Hpwebdeveloper\LaravelStateflow\Attributes\AllowTransition;
use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;

#[DefaultState]
#[StateMetadata(title: 'Pending', description: 'Order is pending confirmation')]
#[AllowTransition(to: Processing::class)]
#[AllowTransition(to: Cancelled::class)]
class Pending extends OrderState
{
    public const NAME = 'pending';

    public static function color(): string
    {
        return 'yellow';
    }
}
```

```php
// app/States/Order/Processing.php
#[StateMetadata(title: 'Processing', description: 'Order is being processed')]
#[AllowTransition(to: Shipped::class)]
#[AllowTransition(to: Cancelled::class)]
class Processing extends OrderState
{
    public const NAME = 'processing';

    public static function color(): string
    {
        return 'blue';
    }
}
```

### Model Becomes Simpler

```php
public static function registerStates(): void
{
    static::addState('state', StateConfig::make(OrderState::class)
        ->default(Pending::class)
        // Transitions are auto-discovered from #[AllowTransition] attributes!
    );
}
```

### When to Use This Approach

✅ **Pros:**

- Each state is self-contained (behavior + transitions in one file)
- Great IDE support — click through to see transitions
- No duplication — each state defined once
- StateFlow auto-discovers attributes

❌ **Cons:**

- To see full workflow, must open multiple files
- Transitions are "scattered" across state classes

> 📁 **See it in action:** [States/Order/](https://github.com/HPWebdeveloper/laravel-stateflow-demo/tree/main/app/States/Order)

---

## Approach 3: Hybrid — Enum for Topology, Classes for Behavior

Use a PHP enum to define the **workflow topology** (which states exist and how they connect), while state classes handle **behavior** (colors, icons, metadata, permissions).

### Why Hybrid?

This approach gives you:

- **Single file** showing the entire workflow graph
- **State classes** still handle all metadata and behavior
- **Clear separation**: Enum = "what can happen", Class = "what it looks/acts like"

### Example: Booking Workflow (from Demo)

#### The Enum — Defines Topology

```php
// app/Enums/BookingWorkflow.php
namespace App\Enums;

use App\States\Booking\{Draft, Confirmed, Paid, Fulfilled, Cancelled, Expired};

/**
 * Booking workflow topology.
 *
 * Transition graph:
 *   draft → confirmed, expired
 *   confirmed → paid, cancelled, expired
 *   paid → fulfilled, cancelled
 *   fulfilled, cancelled, expired → (final states)
 */
enum BookingWorkflow: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Map enum case to state class.
     */
    public function stateClass(): string
    {
        return match ($this) {
            self::Draft => Draft::class,
            self::Confirmed => Confirmed::class,
            self::Paid => Paid::class,
            self::Fulfilled => Fulfilled::class,
            self::Cancelled => Cancelled::class,
            self::Expired => Expired::class,
        };
    }

    /**
     * Define transitions FROM this state.
     */
    public function canTransitionTo(): array
    {
        return match ($this) {
            self::Draft => [Confirmed::class, Expired::class],
            self::Confirmed => [Paid::class, Cancelled::class, Expired::class],
            self::Paid => [Fulfilled::class, Cancelled::class],
            // Final states
            self::Fulfilled, self::Cancelled, self::Expired => [],
        };
    }

    /**
     * Get all state classes for registration.
     */
    public static function stateClasses(): array
    {
        return array_map(fn (self $case) => $case->stateClass(), self::cases());
    }

    /**
     * Get transitions in format for StateConfig::allowTransitionsFromArray().
     */
    public static function transitions(): array
    {
        $transitions = [];
        foreach (self::cases() as $case) {
            foreach ($case->canTransitionTo() as $targetClass) {
                $transitions[] = [
                    'from' => $case->stateClass(),
                    'to' => $targetClass,
                ];
            }
        }
        return $transitions;
    }
}
```

#### State Classes — Handle Behavior

```php
// app/States/Booking/Draft.php
#[DefaultState]
#[StateMetadata(title: 'Draft', description: 'Booking is in draft')]
class Draft extends BookingState
{
    public const NAME = 'draft';

    public static function color(): string { return 'gray'; }
    public static function icon(): string { return 'file-edit'; }
}

// app/States/Booking/Confirmed.php
#[StateMetadata(title: 'Confirmed', description: 'Booking confirmed by customer')]
class Confirmed extends BookingState
{
    public const NAME = 'confirmed';

    public static function color(): string { return 'blue'; }
    public static function icon(): string { return 'check-circle'; }
}

// app/States/Booking/Paid.php
#[StateMetadata(title: 'Paid', description: 'Payment received')]
class Paid extends BookingState
{
    public const NAME = 'paid';

    public static function color(): string { return 'green'; }
    public static function icon(): string { return 'credit-card'; }
}
```

Notice: **No `#[AllowTransition]` attributes** — transitions are defined in the enum!

#### Model Uses the Enum

```php
// app/Models/Booking.php
use App\Enums\BookingWorkflow;
use App\States\Booking\{BookingState, Draft};

class Booking extends Model implements HasStatesContract
{
    use HasStates, HasStateHistory;

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

### When to Use This Approach

✅ **Pros:**

- Entire workflow visible in one file (the enum)
- Easy to visualize and document
- Share workflow across multiple models
- Great for generating diagrams

❌ **Cons:**

- Two files to maintain (enum + state classes)
- Must keep enum values synced with state `NAME` constants

> 📁 **See it in action:**
>
> - [BookingWorkflow.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Enums/BookingWorkflow.php) (enum)
> - [Booking.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Booking.php) (model)
> - [States/Booking/](https://github.com/HPWebdeveloper/laravel-stateflow-demo/tree/main/app/States/Booking) (state classes)

---

## Scaffolding Commands

StateFlow provides Artisan commands to quickly scaffold your state machine.

### Create States (Default Approach)

```bash
# Create base state + individual states
php artisan make:state OrderState --states=Pending,Processing,Shipped,Delivered,Cancelled
```

This creates state classes with `#[AllowTransition]` placeholders that you can fill in.

### Create States with Enum (Hybrid Approach)

```bash
# Create states + enum scaffold
php artisan make:state BookingState --states=Draft,Confirmed,Paid,Fulfilled,Cancelled,Expired --transitions=enum
```

This creates:

- All state classes
- An enum file with `stateClasses()`, `canTransitionTo()`, and `transitions()` methods

### Sync Existing States to Enum

If you've already created state classes and want to generate an enum:

```bash
php artisan stateflow:sync-enum App\\States\\Booking\\BookingState --enum=App\\Enums\\BookingWorkflow
```

This scans your state classes and generates a matching enum.

> ⚠️ **Naming Convention:** By default, `stateflow:sync-enum` creates an enum named `{BaseStateClass}Status` (e.g., `BookingState` → `BookingStateStatus`). Use the `--enum` option to specify a custom name like `BookingWorkflow`.

> ⚠️ **Directory Requirement:** The sync command only discovers state classes in the **same directory** as the base state class. When adding new states, use the full namespace:
>
> ```bash
> php artisan make:state Processing --extends=App\\States\\Booking\\BookingState
> ```

---

## Mixing Approaches

StateFlow **merges all transition definitions** together. You can combine approaches:

```php
public static function registerStates(): void
{
    static::addState('state', StateConfig::make(OrderState::class)
        ->default(Pending::class)
        // From enum
        ->registerStates(OrderStatus::stateClasses())
        ->allowTransitionsFromArray(OrderStatus::transitions())
        // Add extra transition not in enum
        ->allowTransition(Processing::class, OnHold::class)
    );
}
```

State classes can also declare additional transitions via attributes:

```php
// This transition is ALSO respected, even if using enum approach
#[AllowTransition(to: Refunded::class)]
class Cancelled extends OrderState { }
```

---

## Comparison Table

| Feature                   | Explicit                                                                                             | Attributes                                                                                                     | Hybrid Enum                                                                                              |
| ------------------------- | ---------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| **Workflow visibility**   | Model file                                                                                           | Scattered across state files                                                                                   | Single enum file                                                                                         |
| **Self-contained states** | ❌                                                                                                   | ✅                                                                                                             | Partially (behavior only)                                                                                |
| **Easy to add states**    | Edit model                                                                                           | Create file + add attributes                                                                                   | Create file + edit enum                                                                                  |
| **IDE navigation**        | Model → State                                                                                        | State → State                                                                                                  | Enum → All transitions                                                                                   |
| **Diagram generation**    | Manual                                                                                               | Parse attributes                                                                                               | Simple enum iteration                                                                                    |
| **Reuse across models**   | Copy/paste                                                                                           | Copy state classes                                                                                             | Share enum                                                                                               |
| **Demo example**          | [Order.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Order.php) | [Pending.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/States/Order/Pending.php) | [Booking.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Models/Booking.php) |

---

## Our Recommendations

### For Most Projects: Attributes (Approach 2)

The attribute-based approach is recommended because:

- Each state is completely self-contained
- IDE support is excellent
- Already built into StateFlow
- No synchronization between files

### For Complex Workflows: Hybrid Enum (Approach 3)

Consider the hybrid enum approach when:

- You need to see the entire workflow at a glance
- You're generating documentation or diagrams
- Multiple models share the same workflow
- Your team prefers centralized definitions

### For Simple/Small Workflows: Explicit (Approach 1)

The explicit approach works well when:

- You have a simple workflow (3-5 states)
- You prefer everything in one file
- You don't need state class behavior beyond storage

---

## Key Takeaways

1. **StateFlow doesn't force a single approach** — choose what fits your team
2. **Approaches can be mixed** — transitions from all sources are merged
3. **State classes always handle behavior** — colors, icons, metadata, permissions
4. **Enums are optional** — only use if you need centralized topology
5. **Both demo examples work** — Orders (explicit/attributes) and Bookings (hybrid enum)

> 💡 **Start simple, evolve as needed.** Begin with explicit transitions, add attributes as your workflow grows, and consider an enum if you need centralized visualization.
