<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Concerns;

use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait to add state history capabilities to models.
 *
 * Add this trait to models that use HasStates to enable history tracking.
 * Provides methods for querying and analyzing transition history.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasStateHistory
{
    /**
     * Get all state history entries for this model.
     */
    public function stateHistory(): MorphMany
    {
        $historyModel = config('laravel-stateflow.history.model', StateHistory::class);

        return $this->morphMany($historyModel, 'model');
    }

    /**
     * Get state history for a specific field.
     *
     * @return Collection<int, StateHistory>
     */
    public function getStateHistoryFor(?string $field = null): Collection
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->latestFirst()->get();
    }

    /**
     * Get paginated state history.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getStateHistoryPaginated(?string $field = null, int $perPage = 15)
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->latestFirst()->paginate($perPage);
    }

    /**
     * Get the last transition for this model.
     */
    public function getLastTransition(?string $field = null): ?StateHistory
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->latestFirst()->first();
    }

    /**
     * Get the first transition for this model (when state was initially set).
     */
    public function getFirstTransition(?string $field = null): ?StateHistory
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->oldestFirst()->first();
    }

    /**
     * Get the N most recent transitions.
     *
     * @return Collection<int, StateHistory>
     */
    public function getRecentTransitions(int $count = 5, ?string $field = null): Collection
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->latestFirst()->limit($count)->get();
    }

    /**
     * Get all transitions performed by a specific user.
     *
     * @return Collection<int, StateHistory>
     */
    public function getTransitionsByPerformer(Authenticatable|int $performer, ?string $field = null): Collection
    {
        $query = $this->stateHistory()->byPerformer($performer);

        if ($field) {
            $query->forField($field);
        }

        return $query->latestFirst()->get();
    }

    /**
     * Get transitions to a specific state.
     *
     * @return Collection<int, StateHistory>
     */
    public function getTransitionsToState(string $state, ?string $field = null): Collection
    {
        $query = $this->stateHistory()->toState($state);

        if ($field) {
            $query->forField($field);
        }

        return $query->latestFirst()->get();
    }

    /**
     * Get transitions from a specific state.
     *
     * @return Collection<int, StateHistory>
     */
    public function getTransitionsFromState(string $state, ?string $field = null): Collection
    {
        $query = $this->stateHistory()->fromState($state);

        if ($field) {
            $query->forField($field);
        }

        return $query->latestFirst()->get();
    }

    /**
     * Count total transitions for this model.
     */
    public function countTransitions(?string $field = null): int
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->count();
    }

    /**
     * Check if model was ever in a specific state.
     */
    public function wasEverInState(string $state, ?string $field = null): bool
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->where(function ($q) use ($state) {
            $q->where('from_state', $state)
                ->orWhere('to_state', $state);
        })->exists();
    }

    /**
     * Check if model transitioned from one state to another.
     */
    public function hasTransitionedFromTo(string $fromState, string $toState, ?string $field = null): bool
    {
        $query = $this->stateHistory()
            ->fromState($fromState)
            ->toState($toState);

        if ($field) {
            $query->forField($field);
        }

        return $query->exists();
    }

    /**
     * Get the time spent in current state.
     */
    public function getTimeInCurrentState(?string $field = null): ?string
    {
        $lastTransition = $this->getLastTransition($field);

        if (! $lastTransition) {
            // Check created_at as fallback (model was created in this state)
            if (isset($this->created_at)) {
                return $this->created_at->diffForHumans(now(), true);
            }

            return null;
        }

        return $lastTransition->created_at->diffForHumans(now(), true);
    }

    /**
     * Get the timestamp when model entered current state.
     */
    public function getCurrentStateEnteredAt(?string $field = null): ?\Carbon\Carbon
    {
        $lastTransition = $this->getLastTransition($field);

        if (! $lastTransition) {
            // Fall back to created_at
            return $this->created_at ?? null;
        }

        return $lastTransition->created_at;
    }

    /**
     * Get duration between two specific transitions in seconds.
     */
    public function getDurationBetweenStates(
        string $fromState,
        string $toState,
        ?string $field = null
    ): ?int {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        $fromTransition = (clone $query)->toState($fromState)->oldestFirst()->first();
        $toTransition = (clone $query)->toState($toState)->oldestFirst()->first();

        if (! $fromTransition || ! $toTransition) {
            return null;
        }

        return (int) abs($fromTransition->created_at->diffInSeconds($toTransition->created_at));
    }

    /**
     * Get duration from a state to current state.
     */
    public function getDurationFromState(string $state, ?string $field = null): ?int
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        $transition = $query->toState($state)->oldestFirst()->first();

        if (! $transition) {
            return null;
        }

        return (int) abs($transition->created_at->diffInSeconds(now()));
    }

    /**
     * Get history timeline with formatted output.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStateTimeline(?string $field = null): array
    {
        return $this->getStateHistoryFor($field)
            ->map(fn (StateHistory $history) => $history->toSummaryArray())
            ->toArray();
    }

    /**
     * Get unique states this model has been in.
     *
     * @return array<string>
     */
    public function getUniqueStates(?string $field = null): array
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        $fromStates = $query->pluck('from_state')->toArray();
        $toStates = $query->pluck('to_state')->toArray();

        return array_unique(array_merge($fromStates, $toStates));
    }

    /**
     * Get transition count grouped by state.
     *
     * @return array<string, int>
     */
    public function getTransitionCountsByState(?string $field = null): array
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->get()
            ->groupBy('to_state')
            ->map(fn ($group) => $group->count())
            ->toArray();
    }

    /**
     * Check if there are any automated transitions.
     */
    public function hasAutomatedTransitions(?string $field = null): bool
    {
        $query = $this->stateHistory()->automated();

        if ($field) {
            $query->forField($field);
        }

        return $query->exists();
    }

    /**
     * Delete all history for this model (use with caution).
     */
    public function clearStateHistory(?string $field = null): int
    {
        $query = $this->stateHistory();

        if ($field) {
            $query->forField($field);
        }

        return $query->delete();
    }
}
