<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Query;

use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * State statistics and aggregation helpers.
 *
 * Provides methods for calculating state distribution, transition patterns,
 * and time-based analytics.
 *
 * @example
 * $counts = StateStatistics::countByState(Post::class);
 * $percentages = StateStatistics::percentageByState(Post::class);
 * $avgTime = StateStatistics::averageTimeInState(Post::class, 'review');
 */
class StateStatistics
{
    /**
     * Count models by state.
     *
     * @param  class-string<Model&HasStatesContract>  $modelClass
     * @return Collection<string, int>
     */
    public static function countByState(string $modelClass, ?string $field = null): Collection
    {
        $field ??= 'state';

        return $modelClass::query()
            ->select($field, DB::raw('count(*) as count'))
            ->groupBy($field)
            ->pluck('count', $field);
    }

    /**
     * Get percentage distribution by state.
     *
     * @param  class-string<Model&HasStatesContract>  $modelClass
     * @return Collection<string, float>
     */
    public static function percentageByState(string $modelClass, ?string $field = null): Collection
    {
        $field ??= 'state';

        $counts = static::countByState($modelClass, $field);
        $total = $counts->sum();

        if ($total === 0) {
            return collect();
        }

        return $counts->map(fn (int $count) => round(($count / $total) * 100, 2));
    }

    /**
     * Get average time spent in a state (in seconds).
     *
     * Calculates the average duration between entering and exiting a state.
     * Requires history tracking to be enabled.
     *
     * @param  class-string<Model&HasStatesContract>  $modelClass
     */
    public static function averageTimeInState(
        string $modelClass,
        string $state,
        ?string $field = null
    ): ?float {
        $field ??= 'state';
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        // Get model type string
        $modelType = $modelClass;

        // This requires joining state_histories to find enter and exit times
        $result = DB::table("{$historyTable} as enter")
            ->join("{$historyTable} as exit", function ($join) {
                $join->on('enter.model_type', '=', 'exit.model_type')
                    ->on('enter.model_id', '=', 'exit.model_id')
                    ->on('enter.field', '=', 'exit.field')
                    ->whereColumn('exit.created_at', '>', 'enter.created_at');
            })
            ->where('enter.model_type', $modelType)
            ->where('enter.field', $field)
            ->where('enter.to_state', $state)
            ->where('exit.from_state', $state)
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, enter.created_at, exit.created_at)) as avg_seconds')
            ->first();

        return $result?->avg_seconds;
    }

    /**
     * Get most common transition paths.
     *
     * Returns the most frequently occurring from->to state transitions.
     *
     * @param  class-string<Model&HasStatesContract>  $modelClass
     * @return Collection<int, array{from: string, to: string, count: int}>
     */
    public static function mostCommonTransitions(
        string $modelClass,
        ?string $field = null,
        int $limit = 10
    ): Collection {
        $field ??= 'state';
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return DB::table($historyTable)
            ->select('from_state', 'to_state', DB::raw('count(*) as count'))
            ->where('model_type', $modelClass)
            ->where('field', $field)
            ->groupBy('from_state', 'to_state')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'from' => $row->from_state,
                'to' => $row->to_state,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * Get transition count over time.
     *
     * Groups transitions by time period (hour, day, week, month).
     *
     * @param  class-string<Model&HasStatesContract>  $modelClass
     * @return Collection<string, int>
     */
    public static function transitionCountOverTime(
        string $modelClass,
        ?string $field = null,
        string $groupBy = 'day'
    ): Collection {
        $field ??= 'state';
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        $dateFormat = match ($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return DB::table($historyTable)
            ->select(DB::raw("strftime('{$dateFormat}', created_at) as period"), DB::raw('count(*) as count'))
            ->where('model_type', $modelClass)
            ->where('field', $field)
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('count', 'period')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * Get models stuck in a state for too long.
     *
     * Returns models that have been in the specified state for longer than
     * the given number of hours without transitioning out.
     *
     * @param  class-string<Model&HasStatesContract>  $modelClass
     * @return Collection<int, Model>
     */
    public static function stuckInState(
        string $modelClass,
        string $state,
        int $hours,
        ?string $field = null
    ): Collection {
        $field ??= 'state';
        $threshold = now()->subHours($hours);
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return $modelClass::query()
            ->where($field, $state)
            ->whereHas('stateHistory', function ($query) use ($field, $state, $threshold, $historyTable) {
                $query->where("{$historyTable}.field", $field)
                    ->where("{$historyTable}.to_state", $state)
                    ->where("{$historyTable}.created_at", '<=', $threshold);
            })
            ->get();
    }

    /**
     * Get state transition frequency for a specific model.
     *
     * @param  Model&HasStatesContract  $model
     */
    public static function transitionCountForModel(Model $model, ?string $field = null): int
    {
        $field ??= 'state';
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return $model->stateHistory()
            ->where("{$historyTable}.field", $field)
            ->count();
    }

    /**
     * Get the time since last transition for a model.
     *
     * @param  Model&HasStatesContract  $model
     */
    public static function timeSinceLastTransition(Model $model, ?string $field = null): ?int
    {
        $field ??= 'state';

        $lastTransition = $model->getLastTransition($field);

        if (! $lastTransition) {
            return null;
        }

        return (int) $lastTransition->created_at->diffInSeconds(now());
    }

    /**
     * Get models with the most transitions.
     *
     * @param  class-string<Model&HasStatesContract>  $modelClass
     * @return Collection<int, array{model_id: int, count: int}>
     */
    public static function modelsWithMostTransitions(
        string $modelClass,
        ?string $field = null,
        int $limit = 10
    ): Collection {
        $field ??= 'state';
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return DB::table($historyTable)
            ->select('model_id', DB::raw('count(*) as count'))
            ->where('model_type', $modelClass)
            ->where('field', $field)
            ->groupBy('model_id')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'model_id' => (int) $row->model_id,
                'count' => (int) $row->count,
            ]);
    }
}
