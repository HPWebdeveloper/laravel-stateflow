# Role-Based & Policy-Based Permissions

Laravel StateFlow provides flexible permission control for state transitions. You can use role-based permissions, Laravel policies, or combine both approaches. **Permissions are optional** — if you don't configure any permission constraints, all transitions will be allowed.

## Role-Based Permissions

Role-based permissions allow you to control which user roles can transition a model to specific states. You can define permitted roles directly in your state classes using either constants or attributes.

### Using Constants

```php
class Shipped extends OrderState
{
    public const NAME = 'shipped';
    public const PERMITTED_ROLES = ['admin', 'warehouse'];
}
```

### Using Attributes (Recommended)

```php
#[StatePermission(roles: ['admin', 'warehouse'])]
class Shipped extends OrderState
{
    public const NAME = 'shipped';
}
```

The attribute approach is recommended as it's more explicit and works better with IDE auto-completion.

### Checking Permissions

StateFlow provides convenient methods to check if a user has permission to transition to a specific state:

```php
// Check if a specific user can transition
$order->userCanTransitionTo($user, 'shipped');

// Check if the current authenticated user can transition
$order->currentUserCanTransitionTo('shipped');

// Get only states the current user can transition to (automatically filtered)
$order->getNextStates(); // Only returns states the authenticated user can access
```

### Integration with Spatie Laravel-Permission

If you're using [Spatie Laravel-Permission](https://github.com/spatie/laravel-permission), StateFlow integrates seamlessly. The `hasRole()` method is called on your User model, which Spatie provides:

```php
// Your User model with Spatie's HasRoles trait
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles; // From Spatie Laravel-Permission
}

// StateFlow will automatically call $user->hasRole('warehouse')
// when checking if user can transition to a state with PERMITTED_ROLES
```

**Without Spatie Laravel-Permission:** You can implement your own `hasRole()` method on your User model:

```php
class User extends Authenticatable
{
    public function hasRole(string $role): bool
    {
        return $this->role === $role; // Simple column-based
        // Or your custom logic:
        // return $this->roles->contains('name', $role);
    }
}
```

## Policy-Based Permissions

For more complex authorization logic beyond simple role checks, you can use Laravel's native policy system. This is useful when you need to check additional conditions like ownership, status, or business rules.

### Enable Policy-Based Permissions

First, enable policy-based permissions in your config:

```php
// config/laravel-stateflow.php
'permissions' => [
    'policy_based' => true,
],
```

### Create Policy Methods

Create policy methods following the naming convention `transitionTo{StateName}` (in PascalCase):

```php
// app/Policies/OrderPolicy.php
class OrderPolicy
{
    /**
     * Determine if the user can transition the order to Processing state.
     */
    public function transitionToProcessing(User $user, Order $order): bool
    {
        // Custom logic: only order owner or admin can process
        return $user->id === $order->user_id || $user->hasRole('admin');
    }

    /**
     * Determine if the user can transition the order to Shipped state.
     */
    public function transitionToShipped(User $user, Order $order): bool
    {
        // Only warehouse staff can ship
        return $user->hasRole('warehouse');
    }

    /**
     * Determine if the user can transition the order to Cancelled state.
     */
    public function transitionToCancelled(User $user, Order $order): bool
    {
        // Owner can cancel pending orders, admin can cancel any
        return $user->hasRole('admin') ||
            ($user->id === $order->user_id && $order->state->name() === 'pending');
    }
}
```

### How Policy Methods are Resolved

StateFlow automatically generates the policy method name from the state class name:

- State class: `Shipped` → Policy method: `transitionToShipped`
- State class: `Processing` → Policy method: `transitionToProcessing`
- State class: `PendingApproval` → Policy method: `transitionToPendingApproval`

The policy method receives:

1. `$user` - The user attempting the transition
2. `$model` - The model being transitioned (e.g., `$order`)

## Combining Both Approaches

You can use both role-based and policy-based permissions together. When both are enabled, **both checks must pass** for the transition to be allowed:

```php
// State class with role requirement
#[StatePermission(roles: ['warehouse', 'admin'])]
class Shipped extends OrderState {}

// Policy with additional business logic
class OrderPolicy
{
    public function transitionToShipped(User $user, Order $order): bool
    {
        // Additional check: order must be paid
        return $order->payment_status === 'paid';
    }
}
```

In this example, to ship an order:

1. ✅ User must have `warehouse` or `admin` role (role-based check)
2. ✅ AND order must be paid (policy-based check)

This combination is powerful for implementing layered security:

- **Role-based**: Quick, declarative permission at the state level
- **Policy-based**: Complex, context-aware business rules

## Configuration Options

You can customize the permission system behavior in `config/laravel-stateflow.php`:

```php
'permissions' => [
    // Enable/disable permission checking globally
    'enabled' => env('STATEFLOW_PERMISSIONS_ENABLED', true),

    // Throw exception on unauthorized transition (false = return false silently)
    'throw_on_unauthorized' => true,

    // Enable role-based permission checking
    'role_based' => true,

    // Enable policy-based permission checking (Laravel Gate/Policy)
    'policy_based' => false,

    // The attribute name on the user model for role (used by role-based checker)
    'user_role_attribute' => 'role',

    // Prefix for policy ability names
    'policy_ability_prefix' => 'transitionTo',
],
```

## Demo Implementation

The [laravel-stateflow-demo](https://github.com/HPWebdeveloper/laravel-stateflow-demo) repository demonstrates both permission approaches in action:

- **Role-based permissions:** See [Pending.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/States/Order/Pending.php) and [Processing.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/States/Order/Processing.php) for `#[StatePermission]` attribute usage
- **Policy authorization:** See [OrderPolicy.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Policies/OrderPolicy.php) for standard Laravel policy patterns with complex business logic
- **Controller integration:** See [OrderController.php](https://github.com/HPWebdeveloper/laravel-stateflow-demo/blob/main/app/Http/Controllers/OrderController.php) for how the demo authorizes transitions before executing them

## Common Patterns

### Pattern 1: Simple Role-Based Access

Use when you only need to restrict states by user roles:

```php
#[StatePermission(roles: ['admin'])]
class Published extends PostState {}

#[StatePermission(roles: ['author', 'editor'])]
class Draft extends PostState {}
```

### Pattern 2: Ownership + Role Checks

Use policies when you need to verify ownership:

```php
class PostPolicy
{
    public function transitionToPublished(User $user, Post $post): bool
    {
        // Author can publish their own posts, or editor can publish any
        return $post->author_id === $user->id || $user->hasRole('editor');
    }
}
```

### Pattern 3: State-Dependent Logic

Use policies for transitions that depend on current state or other conditions:

```php
class OrderPolicy
{
    public function transitionToCancelled(User $user, Order $order): bool
    {
        // Can only cancel if still pending
        if ($order->state->name() !== 'pending') {
            return false;
        }

        // Owner or admin can cancel
        return $order->user_id === $user->id || $user->hasRole('admin');
    }
}
```

## Troubleshooting

### Permission Always Denied

1. **Check if permissions are enabled:**

   ```php
   // config/laravel-stateflow.php
   'permissions' => ['enabled' => true]
   ```

2. **Verify the user model has required methods:**

   ```php
   // For role-based: $user->hasRole('admin') or $user->role === 'admin'
   ```

3. **Check policy is registered:**
   ```php
   // app/Providers/AuthServiceProvider.php
   protected $policies = [
       Order::class => OrderPolicy::class,
   ];
   ```

### Policy Method Not Called

1. Ensure `policy_based` is enabled in config
2. Verify policy method name matches convention: `transitionTo{StateName}`
3. Check that the policy is properly registered in `AuthServiceProvider`

### Both Checks Required

Remember: When both role-based and policy-based are enabled, **BOTH must pass**. To allow either:

- Disable one approach in config, OR
- Implement your custom `PermissionChecker` class

---

> 💡 **Tip:** Start with role-based permissions for simple cases, then add policies when you need complex business logic. You can always migrate from simple to complex as your application grows.
