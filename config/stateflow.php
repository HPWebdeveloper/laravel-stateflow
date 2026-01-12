<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default State Field
    |--------------------------------------------------------------------------
    |
    | The default database column name used for storing state values.
    | Can be overridden per-model using the $stateField property.
    |
    */
    'default_state_field' => 'state',

    /*
    |--------------------------------------------------------------------------
    | State Classes Directory
    |--------------------------------------------------------------------------
    |
    | The default directory where state classes are located relative to app/.
    | Used by artisan commands for generating state classes.
    |
    */
    'states_directory' => 'States',

    /*
    |--------------------------------------------------------------------------
    | Transition History
    |--------------------------------------------------------------------------
    |
    | Configuration for transition history tracking.
    |
    */
    'history' => [
        // Enable/disable transition history recording globally
        'enabled' => env('STATEFLOW_HISTORY_ENABLED', true),

        // Table name for storing transition history
        'table' => env('STATEFLOW_HISTORY_TABLE', 'state_transition_history'),

        // Model class for transition history (can be overridden)
        'model' => \Hpwebdeveloper\LaravelStateflow\Models\StateTransitionHistory::class,

        // Automatically prune old history records (days, null = keep forever)
        'prune_after_days' => env('STATEFLOW_HISTORY_PRUNE_DAYS', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission System
    |--------------------------------------------------------------------------
    |
    | Configuration for role-based transition permissions.
    |
    */
    'permissions' => [
        // Enable/disable permission checking globally
        'enabled' => env('STATEFLOW_PERMISSIONS_ENABLED', true),

        // How to resolve user role (method name on User model, or closure in ServiceProvider)
        'role_resolver' => 'role', // Will call $user->role or $user->role()

        // Permission checker class (can be overridden)
        'checker' => \Hpwebdeveloper\LaravelStateflow\Services\DefaultPermissionChecker::class,

        // Throw exception on unauthorized transition (false = return false silently)
        'throw_on_unauthorized' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Toggle individual events on or off.
    | Events can be listened to for side effects, logging, notifications, etc.
    |
    */
    'events' => [
        'transition_initiated' => env('STATEFLOW_EVENT_INITIATED', true),
        'transition_completed' => env('STATEFLOW_EVENT_COMPLETED', true),
        'transition_failed' => env('STATEFLOW_EVENT_FAILED', true),
        'state_changed' => env('STATEFLOW_EVENT_STATE_CHANGED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | State Resource Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for state UI metadata when not specified in state class.
    |
    */
    'resource_defaults' => [
        'color' => 'gray',
        'icon' => null,
        'description' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | Settings for state validation rules.
    |
    */
    'validation' => [
        // Include permission check in validation rule
        'check_permissions' => true,

        // Custom validation message key prefix
        'message_key' => 'stateflow',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Builder
    |--------------------------------------------------------------------------
    |
    | Settings for Eloquent query builder integration.
    |
    */
    'query' => [
        // Register global scopes automatically
        'register_scopes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache settings for state configuration and metadata.
    |
    */
    'cache' => [
        // Enable caching of state configurations
        'enabled' => env('STATEFLOW_CACHE_ENABLED', false),

        // Cache key prefix
        'prefix' => 'stateflow',

        // Cache TTL in seconds (null = forever until cleared)
        'ttl' => env('STATEFLOW_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override model classes used by StateFlow.
    |
    */
    'models' => [
        'history' => \Hpwebdeveloper\LaravelStateflow\Models\StateTransitionHistory::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features.
    |
    */
    'features' => [
        // Enable transition history recording
        'history' => env('STATEFLOW_FEATURE_HISTORY', true),

        // Enable role-based permissions
        'permissions' => env('STATEFLOW_FEATURE_PERMISSIONS', true),

        // Enable state resources (UI metadata)
        'resources' => env('STATEFLOW_FEATURE_RESOURCES', true),

        // Enable PHP 8+ attribute support
        'attributes' => env('STATEFLOW_FEATURE_ATTRIBUTES', true),
    ],
];
