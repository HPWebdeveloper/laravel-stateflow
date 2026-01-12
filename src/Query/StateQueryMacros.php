<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Query;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Register query builder macros for state queries.
 *
 * Provides additional query builder methods for state-based ordering,
 * filtering by priority, and history-based aggregations.
 *
 * @example
 * // Order by custom state priority
 * Post::orderByState([Draft::class, Review::class, Published::class])->get();
 *
 * // Include transition count
 * Post::withTransitionCount()->get();
 */
class StateQueryMacros
{
    /**
     * Register all macros.
     */
    public static function register(): void
    {
        static::registerOrderByState();
        static::registerWhereStatePriorityHigherThan();
        static::registerWithTransitionCount();
        static::registerWithLastTransitionDate();
    }

    /**
     * Order by state (custom order).
     *
     * Orders results by a custom state priority list. States not in the
     * list are placed at the end.
     */
    protected static function registerOrderByState(): void
    {
        Builder::macro('orderByState', function (array $stateOrder, ?string $field = null) {
            /** @var Builder $this */
            $field ??= 'state';

            $orderCase = 'CASE '.$field;
            foreach ($stateOrder as $index => $state) {
                $stateName = is_string($state) && class_exists($state) && is_subclass_of($state, StateContract::class)
                    ? $state::name()
                    : $state;
                $orderCase .= " WHEN '{$stateName}' THEN {$index}";
            }
            $orderCase .= ' ELSE 999 END';

            return $this->orderByRaw($orderCase);
        });
    }

    /**
     * Filter by state priority (higher than target).
     *
     * Returns models whose state is higher in priority than the given state,
     * based on the provided priority order array.
     */
    protected static function registerWhereStatePriorityHigherThan(): void
    {
        Builder::macro('whereStatePriorityHigherThan', function (
            string|StateContract $state,
            array $priorityOrder,
            ?string $field = null
        ) {
            /** @var Builder $this */
            $field ??= 'state';

            // Resolve state name
            $stateName = $state instanceof StateContract
                ? $state->name()
                : (class_exists($state) && is_subclass_of($state, StateContract::class) ? $state::name() : $state);

            // Map priority order to names
            $priorityNames = array_map(function ($s) {
                if ($s instanceof StateContract) {
                    return $s->name();
                }

                return is_string($s) && class_exists($s) && is_subclass_of($s, StateContract::class)
                    ? $s::name()
                    : $s;
            }, $priorityOrder);

            $targetIndex = array_search($stateName, $priorityNames, true);

            if ($targetIndex === false || $targetIndex === 0) {
                // State not found or already at highest priority
                return $this->whereRaw('1 = 0');
            }

            $higherStates = array_slice($priorityNames, 0, $targetIndex);

            return $this->whereIn($field, $higherStates);
        });
    }

    /**
     * Include transition count in query results.
     *
     * Adds a `transition_count` attribute containing the number of
     * state transitions for each model.
     */
    protected static function registerWithTransitionCount(): void
    {
        Builder::macro('withTransitionCount', function (?string $field = null) {
            /** @var Builder $this */
            $field ??= 'state';
            $historyTable = config('laravel-stateflow.history.table', 'state_histories');

            return $this->withCount([
                'stateHistory as transition_count' => function ($query) use ($field, $historyTable) {
                    $query->where("{$historyTable}.field", $field);
                },
            ]);
        });
    }

    /**
     * Include last transition date in query results.
     *
     * Adds a `last_transition_at` attribute containing the timestamp
     * of the most recent state transition.
     */
    protected static function registerWithLastTransitionDate(): void
    {
        Builder::macro('withLastTransitionDate', function (?string $field = null) {
            /** @var Builder $this */
            $field ??= 'state';
            $historyTable = config('laravel-stateflow.history.table', 'state_histories');

            return $this->addSelect([
                'last_transition_at' => StateHistory::select('created_at')
                    ->whereColumn('model_id', $this->getModel()->getTable().'.id')
                    ->where('model_type', get_class($this->getModel()))
                    ->where('field', $field)
                    ->orderByDesc('created_at')
                    ->limit(1),
            ]);
        });
    }
}
