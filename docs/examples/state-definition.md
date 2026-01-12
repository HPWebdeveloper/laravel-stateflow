# State Definition

StateFlow supports **three approaches** for defining state metadata. You can use any approach or combine them.

## Approach 1: Methods (Recommended)

The **Method approach** is the most flexible and powerful way to define state metadata:

```php
class Pending extends OrderState
{
    public const NAME = 'pending';           // Database value (required)

    public static function title(): string
    {
        return 'Pending';
    }

    public static function color(): string
    {
        return 'yellow';
    }

    public static function icon(): string
    {
        return 'clock';
    }

    public static function description(): string
    {
        return 'Order is pending confirmation';
    }
}
```

### Why Methods Have the Highest Priority

Methods have the highest priority because:

1. **Flexibility & Logic**: Methods can contain conditional logic and computations, not just return static values:

   ```php
   public static function color(): string
   {
       // Can have logic!
       return $this->model->is_urgent ? 'red' : 'yellow';
   }
   ```

2. **Overriding in Subclasses**: PHP's inheritance model allows child classes to override methods but NOT constants:

   ```php
   // ✅ This works - method override
   class UrgentPending extends Pending
   {
       public static function color(): string
       {
           return 'red'; // Overrides parent's yellow
       }
   }

   // ❌ This doesn't work - can't override constants
   class UrgentPending extends Pending
   {
       public const COLOR = 'red'; // PHP error: Cannot override constant
   }
   ```

3. **Developer Intent**: When a developer explicitly writes a method, it shows **clear, intentional behavior** — especially if overriding default behavior.

4. **Runtime Flexibility**: Methods can access other class properties or even make calculations at runtime.

The hierarchy makes sense:

- **Methods** (most flexible, can override anything)
- **Constants** (simple, good for basic cases)
- **Attributes** (declarative metadata, checked if no method/constant)
- **Config** (global defaults)

This design pattern is called the **Template Method Pattern** — where specific implementations (methods) take precedence over general configurations (constants/attributes/config).

---

## Approach 2: Attributes (Clean & Declarative)

PHP 8+ attributes provide a clean, declarative way to define state metadata:

```php
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\AllowTransition;
use Hpwebdeveloper\LaravelStateflow\Attributes\StatePermission;
use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;

#[DefaultState]
#[StateMetadata(title: 'Pending', color: 'yellow', icon: 'clock', description: 'Order is pending confirmation')]
#[StatePermission(roles: ['admin', 'warehouse'])]
#[AllowTransition(to: Processing::class)]
#[AllowTransition(to: Cancelled::class)]
class Pending extends OrderState
{
    public const NAME = 'pending';
}
```

### Available Attributes

| Attribute            | Purpose                                | Example                                               |
| -------------------- | -------------------------------------- | ----------------------------------------------------- |
| `#[DefaultState]`    | Mark as the default state              | `#[DefaultState]`                                     |
| `#[StateMetadata]`   | Define title, color, icon, description | `#[StateMetadata(title: 'Pending', color: 'yellow')]` |
| `#[StatePermission]` | Define permitted roles                 | `#[StatePermission(roles: ['admin'])]`                |
| `#[AllowTransition]` | Define allowed transitions             | `#[AllowTransition(to: Processing::class)]`           |

---

## Approach 3: Constants (Simple)

For simple cases, you can use class constants:

```php
class Pending extends OrderState
{
    public const NAME = 'pending';           // Database value
    public const TITLE = 'Pending';          // Display title
    public const COLOR = 'yellow';           // UI color
    public const ICON = 'clock';             // UI icon
    public const DESCRIPTION = 'Order is pending confirmation';
    public const IS_DEFAULT = true;          // Default state
    public const NEXT = [Processing::class, Cancelled::class];   // Allowed transitions
    public const PERMITTED_ROLES = ['admin', 'warehouse'];
}
```

### Available Constants

| Constant          | Purpose                                 | Type     |
| ----------------- | --------------------------------------- | -------- |
| `NAME`            | Database value (required)               | `string` |
| `TITLE`           | Display title                           | `string` |
| `COLOR`           | UI color                                | `string` |
| `ICON`            | UI icon identifier                      | `string` |
| `DESCRIPTION`     | Human-readable description              | `string` |
| `IS_DEFAULT`      | Mark as default state                   | `bool`   |
| `NEXT`            | Allowed transition targets              | `array`  |
| `PERMITTED_ROLES` | Roles that can transition to this state | `array`  |

---

## Combining Approaches

You can mix approaches if needed:

```php
#[DefaultState]
#[StatePermission(roles: ['admin', 'warehouse'])]
#[AllowTransition(to: Processing::class)]
#[AllowTransition(to: Cancelled::class)]
class Pending extends OrderState
{
    public const NAME = 'pending';

    public static function title(): string { return 'Pending'; }
    public static function color(): string { return 'yellow'; }
    public static function icon(): string { return 'clock'; }
    public static function description(): string { return 'Order is pending confirmation'; }
}
```

---

## Priority Order

If you define the same property multiple ways, StateFlow checks in this order:

1. **Methods** (highest priority) — e.g., `public static function color(): string`
2. **Constants** — e.g., `public const COLOR = 'yellow';`
3. **Attributes** — e.g., `#[StateMetadata(color: 'yellow')]`
4. **Config defaults** (lowest priority)

---

## Demo Example

The [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo) uses a **combined approach** — attributes for static metadata and methods for dynamic values. See [States/Order/](https://github.com/HPWebdeveloper/laravel-stateflow-demo/tree/main/app/States/Order) for complete examples.

```php
// app/States/Order/Pending.php
use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;

#[DefaultState]
#[StateMetadata(
    title: 'Pending',
    description: 'Order is pending confirmation'
)]
class Pending extends OrderState
{
    public const NAME = 'pending';

    public static function color(): string
    {
        return 'yellow';
    }

    public static function icon(): string
    {
        return 'clock';
    }
}
```
