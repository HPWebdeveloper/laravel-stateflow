<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Actions\CheckTransitionPermission;
use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Models\StateTransitionHistory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Main StateFlow class for static configuration.
 *
 * This class provides static methods to configure StateFlow behavior:
 * - Model customization
 * - Route/migration control
 * - Permission checker binding
 * - State class resolution
 * - Feature management
 *
 * @example
 * // In AppServiceProvider boot():
 * StateFlow::useHistoryModel(MyHistory::class);
 * StateFlow::ignoreMigrations();
 * StateFlow::permissionCheckerUsing(MyChecker::class);
 * StateFlow::enableFeature('permissions');
 */
class StateFlow
{
    /**
     * Indicates if StateFlow migrations will be registered.
     */
    public static bool $runsMigrations = true;

    /**
     * The history model class.
     */
    public static string $historyModel = StateTransitionHistory::class;

    /**
     * The permission checker class.
     */
    public static ?string $permissionChecker = null;

    /**
     * Custom permission checker instance.
     */
    protected static ?PermissionChecker $permissionCheckerInstance = null;

    /**
     * Registered state classes by base class.
     *
     * @var array<string, array<class-string<StateContract>>>
     */
    protected static array $registeredStates = [];

    /**
     * Custom transition classes.
     *
     * @var array<string, class-string>
     */
    protected static array $transitions = [];

    /**
     * Runtime feature overrides.
     *
     * @var array<string, bool>
     */
    protected static array $featureOverrides = [];

    /**
     * Configure StateFlow to not register its migrations.
     */
    public static function ignoreMigrations(): static
    {
        static::$runsMigrations = false;

        /** @phpstan-ignore-next-line */
        return new static;
    }

    /**
     * Specify the history model class.
     */
    public static function useHistoryModel(string $model): static
    {
        static::$historyModel = $model;

        /** @phpstan-ignore-next-line */
        return new static;
    }

    /**
     * Get the history model class.
     */
    public static function historyModel(): string
    {
        return static::$historyModel;
    }

    /**
     * Get a new instance of the history model.
     */
    public static function newHistoryModel(): StateTransitionHistory
    {
        $modelClass = static::$historyModel;

        /** @phpstan-ignore-next-line */
        return new $modelClass;
    }

    /**
     * Specify the permission checker class.
     *
     * @param  string  $class  Class implementing PermissionChecker
     */
    public static function permissionCheckerUsing(string $class): void
    {
        static::$permissionChecker = $class;

        app()->singleton(PermissionChecker::class, $class);
    }

    /**
     * Use a specific permission checker instance.
     */
    public static function usePermissionChecker(PermissionChecker $checker): void
    {
        static::$permissionCheckerInstance = $checker;

        app()->instance(PermissionChecker::class, $checker);
    }

    /**
     * Get the registered permission checker instance.
     */
    public static function getPermissionChecker(): ?PermissionChecker
    {
        if (static::$permissionCheckerInstance) {
            return static::$permissionCheckerInstance;
        }

        if (static::$permissionChecker && app()->bound(PermissionChecker::class)) {
            return app(PermissionChecker::class);
        }

        return null;
    }

    /**
     * Register state classes for a base state.
     *
     * @param  array<class-string<StateContract>>  $stateClasses
     */
    public static function registerStates(string $baseStateClass, array $stateClasses): static
    {
        static::$registeredStates[$baseStateClass] = $stateClasses;

        /** @phpstan-ignore-next-line */
        return new static;
    }

    /**
     * Get registered states for a base class.
     *
     * @return array<class-string<StateContract>>
     */
    public static function getRegisteredStates(string $baseStateClass): array
    {
        return static::$registeredStates[$baseStateClass] ?? [];
    }

    /**
     * Register a custom transition class.
     *
     * @param  class-string<StateContract>  $fromState
     * @param  class-string<StateContract>  $toState
     * @param  class-string  $transitionClass
     */
    public static function registerTransition(
        string $fromState,
        string $toState,
        string $transitionClass
    ): static {
        $key = static::createTransitionKey($fromState, $toState);
        static::$transitions[$key] = $transitionClass;

        /** @phpstan-ignore-next-line */
        return new static;
    }

    /**
     * Get custom transition class if registered.
     *
     * @param  class-string<StateContract>  $fromState
     * @param  class-string<StateContract>  $toState
     * @return class-string|null
     */
    public static function getTransitionClass(string $fromState, string $toState): ?string
    {
        $key = static::createTransitionKey($fromState, $toState);

        return static::$transitions[$key] ?? null;
    }

    // -------------------------------------------------------------------------
    // Feature Management
    // -------------------------------------------------------------------------

    /**
     * Check if a feature is enabled.
     */
    public static function hasFeature(string $feature): bool
    {
        // Check runtime overrides first
        if (isset(static::$featureOverrides[$feature])) {
            return static::$featureOverrides[$feature];
        }

        return config("laravel-stateflow.features.{$feature}", false);
    }

    /**
     * Enable a feature at runtime.
     */
    public static function enableFeature(string $feature): void
    {
        static::$featureOverrides[$feature] = true;
    }

    /**
     * Disable a feature at runtime.
     */
    public static function disableFeature(string $feature): void
    {
        static::$featureOverrides[$feature] = false;
    }

    /**
     * Check if history tracking is enabled.
     */
    public static function recordsHistory(): bool
    {
        return static::hasFeature('history') && config('laravel-stateflow.history.enabled', false);
    }

    /**
     * Check if permission checking is enabled.
     */
    public static function checksPermissions(): bool
    {
        return static::hasFeature('permissions') && config('laravel-stateflow.permissions.enabled', true);
    }

    // -------------------------------------------------------------------------
    // Permission Helpers
    // -------------------------------------------------------------------------

    /**
     * Check if a user can transition a model.
     *
     * @param  class-string<StateContract>|StateContract|string  $fromState
     * @param  class-string<StateContract>|StateContract|string  $toState
     */
    public static function userCanTransition(
        Authenticatable $user,
        Model $model,
        string $field,
        string|StateContract $fromState,
        string|StateContract $toState
    ): bool {
        if (! static::checksPermissions()) {
            return true;
        }

        $fromClass = $fromState instanceof StateContract ? $fromState::class : $fromState;
        $toClass = $toState instanceof StateContract ? $toState::class : $toState;

        $data = new TransitionData(
            model: $model,
            field: $field,
            fromState: $fromClass,
            toState: $toClass,
            performer: $user,
        );

        return CheckTransitionPermission::check($data);
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a transition key for the transitions array.
     */
    protected static function createTransitionKey(string $fromState, string $toState): string
    {
        return $fromState.'->'.$toState;
    }

    /**
     * Reset all static configuration (useful for testing).
     */
    public static function reset(): void
    {
        static::$runsMigrations = true;
        static::$historyModel = StateTransitionHistory::class;
        static::$permissionChecker = null;
        static::$permissionCheckerInstance = null;
        static::$registeredStates = [];
        static::$transitions = [];
        static::$featureOverrides = [];
    }
}
