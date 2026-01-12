<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Hpwebdeveloper\LaravelStateflow\Listeners\StateTransitionSubscriber;
use Hpwebdeveloper\LaravelStateflow\Query\StateQueryMacros;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LaravelStateflowServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-stateflow.php', 'laravel-stateflow');

        // Bind permission checker based on config
        $this->app->singleton(PermissionChecker::class, function ($app) {
            // First check for custom checker set via StateFlow
            if (StateFlow::$permissionChecker) {
                return $app->make(StateFlow::$permissionChecker);
            }

            // Build checker based on config (role_based, policy_based settings)
            $checkers = [];

            // Role-based checker (if enabled)
            if (config('laravel-stateflow.permissions.role_based', true)) {
                $checkers[] = new Permissions\RoleBasedChecker(
                    config('laravel-stateflow.permissions.user_role_attribute', 'role')
                );
            }

            // Policy-based checker (if enabled)
            if (config('laravel-stateflow.permissions.policy_based', false)) {
                $checkers[] = new Permissions\PolicyBasedChecker(
                    config('laravel-stateflow.permissions.policy_ability_prefix', 'transitionTo')
                );
            }

            // If no checkers configured, fall back to config checker or default
            if (empty($checkers)) {
                $checkerClass = config('laravel-stateflow.permissions.checker', Services\DefaultPermissionChecker::class);

                return $app->make($checkerClass);
            }

            // Single checker
            if (count($checkers) === 1) {
                return $checkers[0];
            }

            // Multiple checkers - all must pass
            return Permissions\CompositeChecker::all($checkers);
        });

        // Register facade accessor
        $this->app->singleton('stateflow', function () {
            return new StateFlow;
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerEventSubscribers();
        $this->registerQueryMacros();
        $this->registerCommands();
    }

    /**
     * Register artisan commands.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\MakeStateCommand::class,
            Console\MakeTransitionCommand::class,
            Console\StateFlowListCommand::class,
            Console\StateFlowAuditCommand::class,
            Console\SyncEnumCommand::class,
        ]);
    }

    /**
     * Register publishable resources.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes([
            __DIR__.'/../config/laravel-stateflow.php' => config_path('laravel-stateflow.php'),
        ], 'stateflow-config');

        // Migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'stateflow-migrations');

        // Stubs (for artisan commands)
        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs'),
        ], 'stateflow-stubs');
    }

    /**
     * Register migrations.
     */
    protected function registerMigrations(): void
    {
        // Allow disabling migrations via static method
        if (! StateFlow::$runsMigrations) {
            return;
        }

        // Only load if history feature is enabled
        if (StateFlow::hasFeature('history')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Register event subscribers.
     */
    protected function registerEventSubscribers(): void
    {
        // Only register subscriber if events feature is enabled
        if (! StateFlow::hasFeature('events')) {
            return;
        }

        // Only register if subscriber is enabled in config
        if (! config('laravel-stateflow.events.subscriber_enabled', true)) {
            return;
        }

        Event::subscribe(StateTransitionSubscriber::class);
    }

    /**
     * Register query builder macros.
     */
    protected function registerQueryMacros(): void
    {
        StateQueryMacros::register();
    }
}
