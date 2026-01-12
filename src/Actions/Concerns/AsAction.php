<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Actions\Concerns;

/**
 * Provides a simple action pattern similar to lorisleiva/laravel-actions.
 *
 * This is a minimal implementation that allows actions to be called
 * statically via ::run() or instantiated via ::make().
 *
 * If lorisleiva/laravel-actions is installed, you can use their AsAction
 * trait instead for additional features like controllers and jobs.
 */
trait AsAction
{
    /**
     * Create a new instance of the action.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Run the action with the given arguments.
     */
    public static function run(mixed ...$arguments): mixed
    {
        return static::make()->handle(...$arguments);
    }
}
