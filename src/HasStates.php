<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\Contracts\TransitionContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\StateData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Hpwebdeveloper\LaravelStateflow\Exceptions\InvalidStateException;
use Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionNotAllowedException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for Eloquent models with state management.
 *
 * Provides state access, transitions, and query scopes.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasStates
{
    /**
     * State configurations keyed by field.
     *
     * @var array<string, StateConfig>
     */
    protected static array $stateConfigs = [];

    /**
     * Track if states have been registered for this model.
     */
    protected static bool $statesRegistered = false;

    /**
     * Boot the trait.
     */
    public static function bootHasStates(): void
    {
        static::ensureStatesRegistered();

        // Set default state values when creating
        static::creating(function ($model): void {
            foreach (static::$stateConfigs as $field => $config) {
                if (is_null($model->{$field})) {
                    $defaultClass = $config->getDefaultStateClass();
                    if ($defaultClass) {
                        $model->{$field} = $defaultClass::name();
                    }
                }
            }
        });
    }

    /**
     * Initialize the trait for an instance.
     */
    public function initializeHasStates(): void
    {
        static::ensureStatesRegistered();

        // Add casts dynamically for state fields
        foreach (static::$stateConfigs as $field => $config) {
            $this->mergeCasts([
                $field => StateCaster::class.':'.$config->getBaseStateClass(),
            ]);
        }
    }

    /**
     * Ensure states are registered for this model.
     */
    protected static function ensureStatesRegistered(): void
    {
        if (! static::$statesRegistered) {
            static::registerStates();
            static::$statesRegistered = true;
        }
    }

    /**
     * Reset state registration (for testing).
     */
    public static function resetStateRegistration(): void
    {
        static::$stateConfigs = [];
        static::$statesRegistered = false;
    }

    /**
     * Register states for this model.
     *
     * Override this method in your model.
     */
    abstract public static function registerStates(): void;

    /**
     * Add a state configuration.
     */
    protected static function addState(string $field, StateConfig $config): void
    {
        $config->field($field);
        static::$stateConfigs[$field] = $config;
    }

    // -------------------------------------------------------------------------
    // State Access
    // -------------------------------------------------------------------------

    /**
     * Get state configuration for a field.
     *
     * @throws StateConfigurationException
     */
    public static function getStateConfig(string $field): ?StateConfig
    {
        static::ensureStatesRegistered();

        if (! isset(static::$stateConfigs[$field])) {
            throw StateConfigurationException::noStateConfig(static::class, $field);
        }

        return static::$stateConfigs[$field];
    }

    /**
     * Get all state configurations.
     *
     * @return array<string, StateConfig>
     */
    public static function getAllStateConfigs(): array
    {
        static::ensureStatesRegistered();

        return static::$stateConfigs;
    }

    /**
     * Check if a field has state configuration.
     */
    public static function hasStateConfig(string $field): bool
    {
        static::ensureStatesRegistered();

        return isset(static::$stateConfigs[$field]);
    }

    /**
     * Get the current state for a field.
     */
    public function getState(?string $field = null): ?StateContract
    {
        $field = $field ?? $this->getDefaultStateField();

        return $this->{$field};
    }

    /**
     * Get the state name as string.
     */
    public function getStateName(?string $field = null): ?string
    {
        $state = $this->getState($field);

        return $state?->name();
    }

    /**
     * Get the state title.
     */
    public function getStateTitle(?string $field = null): ?string
    {
        $state = $this->getState($field);

        return $state?->title();
    }

    /**
     * Get the state color.
     */
    public function getStateColor(?string $field = null): ?string
    {
        $state = $this->getState($field);

        return $state?->color();
    }

    /**
     * Check if current state matches.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function isState(string|StateContract $state, ?string $field = null): bool
    {
        $currentState = $this->getState($field);
        if (! $currentState) {
            return false;
        }

        // If StateContract instance
        if ($state instanceof StateContract) {
            return $currentState::class === $state::class;
        }

        // If state class name
        if (class_exists($state)) {
            return $currentState instanceof $state;
        }

        // If state name
        return $currentState->name() === $state;
    }

    /**
     * Check if current state is any of the given states.
     *
     * @param  array<class-string<StateContract>|StateContract|string>  $states
     */
    public function isAnyState(array $states, ?string $field = null): bool
    {
        foreach ($states as $state) {
            if ($this->isState($state, $field)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Transitions
    // -------------------------------------------------------------------------

    /**
     * Check if model can transition to a state.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function canTransitionTo(string|StateContract $state, ?string $field = null): bool
    {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);
        $currentState = $this->getState($field);

        if (! $currentState || ! $config) {
            return false;
        }

        $targetClass = $this->resolveStateClassFromConfig($state, $config);

        if (! $targetClass) {
            return false;
        }

        return $config->isTransitionAllowed($currentState::class, $targetClass);
    }

    /**
     * Get available next states.
     *
     * @return array<class-string<StateContract>>
     */
    public function getNextStates(?string $field = null): array
    {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);
        $currentState = $this->getState($field);

        if (! $currentState || ! $config) {
            return [];
        }

        return $config->getAllowedTransitions($currentState::class);
    }

    /**
     * Transition to a new state.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     * @param  array<string, mixed>  $metadata
     *
     * @throws TransitionNotAllowedException
     * @throws InvalidStateException
     */
    public function transitionTo(
        string|StateContract $state,
        ?string $field = null,
        ?string $reason = null,
        array $metadata = []
    ): TransitionResult {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);
        $currentState = $this->getState($field);

        if (! $currentState) {
            throw new InvalidStateException('Model does not have a current state');
        }

        if (! $config) {
            throw StateConfigurationException::noStateConfig(static::class, $field);
        }

        $targetClass = $this->resolveStateClassFromConfig($state, $config);

        if (! $targetClass) {
            throw InvalidStateException::invalidState(
                is_string($state) ? $state : $state::class,
                $config->getBaseStateClass()
            );
        }

        // Check if transition is allowed
        if (! $config->isTransitionAllowed($currentState::class, $targetClass)) {
            // Dispatch TransitionFailed event if events are enabled
            if (config('laravel-stateflow.features.events', true)) {
                event(new TransitionFailed(
                    model: $this,
                    field: $field,
                    fromState: $currentState::name(),
                    toState: $targetClass::name(),
                    error: "Transition from '{$currentState::name()}' to '{$targetClass::name()}' is not allowed.",
                    errorCode: 'TRANSITION_NOT_ALLOWED',
                    performer: auth()->user(),
                    reason: $reason,
                    metadata: $metadata,
                ));
            }

            throw TransitionNotAllowedException::create(
                $currentState::name(),
                $targetClass::name()
            );
        }

        return $this->executeTransition(
            $currentState::class,
            $targetClass,
            $field,
            $config,
            $reason,
            $metadata
        );
    }

    /**
     * Force transition without checking allowed transitions.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     * @param  array<string, mixed>  $metadata
     *
     * @throws InvalidStateException
     */
    public function forceTransitionTo(
        string|StateContract $state,
        ?string $field = null,
        ?string $reason = null,
        array $metadata = []
    ): TransitionResult {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);
        $currentState = $this->getState($field);

        if (! $config) {
            throw StateConfigurationException::noStateConfig(static::class, $field);
        }

        $targetClass = $this->resolveStateClassFromConfig($state, $config);

        if (! $targetClass) {
            throw InvalidStateException::invalidState(
                is_string($state) ? $state : $state::class,
                $config->getBaseStateClass()
            );
        }

        return $this->executeTransition(
            $currentState !== null ? $currentState::class : '',
            $targetClass,
            $field,
            $config,
            $reason,
            $metadata,
            true
        );
    }

    /**
     * Execute a transition.
     *
     * @param  class-string<StateContract>  $fromClass
     * @param  class-string<StateContract>  $toClass
     * @param  array<string, mixed>  $metadata
     */
    protected function executeTransition(
        string $fromClass,
        string $toClass,
        string $field,
        StateConfig $config,
        ?string $reason,
        array $metadata,
        bool $forced = false
    ): TransitionResult {
        // Get custom transition class if defined
        $transitionClass = $config->getTransitionClass($fromClass, $toClass);

        // Create transition instance
        if ($transitionClass && class_exists($transitionClass)) {
            /** @var TransitionContract $transition */
            $transition = new $transitionClass($this, $field, $toClass);
        } else {
            $transition = new Transition($this, $field, $toClass);
        }

        // Set metadata
        $transition->setReason($reason);
        $transition->setMetadata($metadata);

        // Execute
        return $transition->execute();
    }

    /**
     * Transition using the ExecuteTransition action.
     *
     * This method provides the full action-based transition with hooks,
     * validation, and context tracking.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     * @param  array<string, mixed>  $metadata
     */
    public function transitionToWithAction(
        string|StateContract $state,
        ?string $field = null,
        ?string $reason = null,
        array $metadata = []
    ): TransitionResult {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);
        $currentState = $this->getState($field);

        if (! $config) {
            return TransitionResult::failure(
                "No state configuration for field '{$field}'."
            );
        }

        if (! $currentState) {
            return TransitionResult::failure('Current state is null.');
        }

        $targetClass = $this->resolveStateClassFromConfig($state, $config);
        if (! $targetClass) {
            return TransitionResult::failure("Unknown state: {$state}");
        }

        // Check if transition is allowed
        if (! $this->canTransitionTo($targetClass, $field)) {
            return TransitionResult::failure(
                "Transition from '{$currentState->name()}' to '{$targetClass::name()}' is not allowed."
            );
        }

        // Create transition data
        $transitionData = new DTOs\TransitionData(
            model: $this,
            field: $field,
            fromState: get_class($currentState),
            toState: $targetClass,
            performer: auth()->user(),
            reason: $reason,
            metadata: $metadata,
        );

        // Use ExecuteTransition action
        try {
            return Actions\ExecuteTransition::run($transitionData);
        } catch (Exceptions\TransitionException $e) {
            return TransitionResult::failure($e->getMessage());
        }
    }

    /**
     * Validate if a transition can occur.
     *
     * Returns validation result with reasons if invalid.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     * @return array{valid: bool, reasons: array<string>}
     */
    public function validateTransitionTo(
        string|StateContract $state,
        ?string $field = null
    ): array {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);
        $currentState = $this->getState($field);

        if (! $config) {
            return [
                'valid' => false,
                'reasons' => ["No state configuration for field '{$field}'."],
            ];
        }

        if (! $currentState) {
            return [
                'valid' => false,
                'reasons' => ['Current state is null.'],
            ];
        }

        $targetClass = $this->resolveStateClassFromConfig($state, $config);
        if (! $targetClass) {
            return [
                'valid' => false,
                'reasons' => ["Unknown state: {$state}"],
            ];
        }

        // Create transition data
        $transitionData = new DTOs\TransitionData(
            model: $this,
            field: $field,
            fromState: get_class($currentState),
            toState: $targetClass,
            performer: auth()->user(),
        );

        return Actions\ValidateTransition::run($transitionData);
    }

    // -------------------------------------------------------------------------
    // Permission Methods
    // -------------------------------------------------------------------------

    /**
     * Check if a user can transition this model to a state.
     *
     * Checks both transition allowance and user permissions.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function userCanTransitionTo(
        Authenticatable $user,
        string|StateContract $state,
        ?string $field = null
    ): bool {
        $field = $field ?? $this->getDefaultStateField();

        // First check if transition is allowed
        if (! $this->canTransitionTo($state, $field)) {
            return false;
        }

        // Then check permissions
        $currentState = $this->getState($field);
        if (! $currentState) {
            return false;
        }

        $config = static::getStateConfig($field);
        if (! $config) {
            return false;
        }

        $targetClass = $this->resolveStateClassFromConfig($state, $config);
        if (! $targetClass) {
            return false;
        }

        return StateFlow::userCanTransition(
            $user,
            $this,
            $field,
            $currentState,
            $targetClass
        );
    }

    /**
     * Get next states that a user can transition to.
     *
     * Filters available transitions by user permissions.
     *
     * @return array<class-string<StateContract>>
     */
    public function getNextStatesForUser(
        Authenticatable $user,
        ?string $field = null
    ): array {
        $field = $field ?? $this->getDefaultStateField();
        $nextStates = $this->getNextStates($field);

        if (! StateFlow::checksPermissions()) {
            return $nextStates;
        }

        return array_values(array_filter($nextStates, function ($stateClass) use ($user, $field) {
            return $this->userCanTransitionTo($user, $stateClass, $field);
        }));
    }

    /**
     * Get next states for user as StateData DTOs.
     *
     * Returns rich state data objects with UI metadata.
     *
     * @return array<StateData>
     */
    public function getNextStatesDataForUser(
        Authenticatable $user,
        ?string $field = null
    ): array {
        $nextStates = $this->getNextStatesForUser($user, $field);

        return array_map(
            fn (string $stateClass) => StateData::fromStateClass($stateClass),
            $nextStates
        );
    }

    /**
     * Check if any user can perform the transition.
     *
     * Uses the current authenticated user if none provided.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function currentUserCanTransitionTo(
        string|StateContract $state,
        ?string $field = null
    ): bool {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $this->userCanTransitionTo($user, $state, $field);
    }

    /**
     * Get next states for the current authenticated user.
     *
     * @return array<class-string<StateContract>>
     */
    public function getNextStatesForCurrentUser(?string $field = null): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return $this->getNextStatesForUser($user, $field);
    }

    // -------------------------------------------------------------------------
    // Resource Methods
    // -------------------------------------------------------------------------

    /**
     * Get state as resource array.
     *
     * @return array<string, mixed>
     */
    public function getStateResource(?Authenticatable $user = null, ?string $field = null): array
    {
        $state = $this->getState($field);

        if (! $state) {
            return ['name' => null, 'title' => null, 'color' => null];
        }

        $data = DTOs\StateResourceData::fromStateClass(
            get_class($state),
            $this,
            $user ?? auth()->user()
        );

        return $data->toArray();
    }

    /**
     * Get state for UI display.
     *
     * @return array{name: string, title: string, color: string, icon: ?string}|null
     */
    public function getStateForUI(?string $field = null): ?array
    {
        $state = $this->getState($field);

        if (! $state) {
            return null;
        }

        return [
            'name' => $state->name(),
            'title' => $state->title(),
            'color' => $state->color(),
            'icon' => method_exists($state, 'icon') ? $state::icon() : null,
        ];
    }

    /**
     * Get next states for UI display.
     *
     * @return array<array{name: string, title: string, color: string, icon: ?string}>
     */
    public function getNextStatesForUI(
        ?Authenticatable $user = null,
        ?string $field = null
    ): array {
        $user ??= auth()->user();

        $nextStates = $user
            ? $this->getNextStatesForUser($user, $field)
            : $this->getNextStates($field);

        return array_map(function ($stateClass) {
            return [
                'name' => $stateClass::name(),
                'title' => $stateClass::title(),
                'color' => $stateClass::color(),
                'icon' => method_exists($stateClass, 'icon') ? $stateClass::icon() : null,
            ];
        }, $nextStates);
    }

    // -------------------------------------------------------------------------
    // Query Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope to filter by state.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function scopeWhereState(Builder $query, string|StateContract $state, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $stateName = $this->resolveStateName($state);

        return $query->where($field, $stateName);
    }

    /**
     * Scope to filter by multiple states (OR).
     *
     * @param  array<class-string<StateContract>|StateContract|string>  $states
     */
    public function scopeWhereStateIn(Builder $query, array $states, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $stateNames = array_map(fn ($state) => $this->resolveStateName($state), $states);

        return $query->whereIn($field, $stateNames);
    }

    /**
     * Scope to exclude a state.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function scopeWhereStateNot(Builder $query, string|StateContract $state, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $stateName = $this->resolveStateName($state);

        return $query->where($field, '!=', $stateName);
    }

    /**
     * Scope to exclude multiple states.
     *
     * @param  array<class-string<StateContract>|StateContract|string>  $states
     */
    public function scopeWhereStateNotIn(Builder $query, array $states, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $stateNames = array_map(fn ($state) => $this->resolveStateName($state), $states);

        return $query->whereNotIn($field, $stateNames);
    }

    /**
     * Scope to filter by active (non-final) states.
     *
     * Active states are those that can transition to at least one other state.
     */
    public function scopeWhereActiveState(Builder $query, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);

        if (! $config) {
            return $query;
        }

        $activeStates = [];
        foreach ($config->getStates() as $stateClass) {
            $allowedTransitions = $config->getAllowedTransitions($stateClass);
            if (! empty($allowedTransitions)) {
                $activeStates[] = $stateClass::name();
            }
        }

        return $query->whereIn($field, $activeStates);
    }

    /**
     * Scope to filter by final (terminal) states.
     *
     * Final states are those that cannot transition to any other state.
     */
    public function scopeWhereFinalState(Builder $query, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);

        if (! $config) {
            return $query;
        }

        $finalStates = [];
        foreach ($config->getStates() as $stateClass) {
            $allowedTransitions = $config->getAllowedTransitions($stateClass);
            if (empty($allowedTransitions)) {
                $finalStates[] = $stateClass::name();
            }
        }

        return $query->whereIn($field, $finalStates);
    }

    /**
     * Scope to filter by states that can transition to a target state.
     *
     * @param  class-string<StateContract>|StateContract|string  $targetState
     */
    public function scopeWhereCanTransitionTo(Builder $query, string|StateContract $targetState, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);

        if (! $config) {
            return $query;
        }

        $targetClass = $this->resolveStateClassFromConfig($targetState, $config);
        if (! $targetClass) {
            return $query->whereRaw('1 = 0'); // No results
        }

        $statesCanTransition = [];
        foreach ($config->getStates() as $stateClass) {
            if ($config->isTransitionAllowed($stateClass, $targetClass)) {
                $statesCanTransition[] = $stateClass::name();
            }
        }

        if (empty($statesCanTransition)) {
            return $query->whereRaw('1 = 0'); // No results
        }

        return $query->whereIn($field, $statesCanTransition);
    }

    /**
     * Scope to filter by initial/default state.
     */
    public function scopeWhereInitialState(Builder $query, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);

        if (! $config) {
            return $query;
        }

        $defaultClass = $config->getDefaultStateClass();
        if (! $defaultClass) {
            return $query;
        }

        return $query->where($field, $defaultClass::name());
    }

    /**
     * Scope to filter by models not in the initial/default state.
     */
    public function scopeWhereNotInitialState(Builder $query, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $config = static::getStateConfig($field);

        if (! $config) {
            return $query;
        }

        $defaultClass = $config->getDefaultStateClass();
        if (! $defaultClass) {
            return $query;
        }

        return $query->where($field, '!=', $defaultClass::name());
    }

    /**
     * Scope to filter by models that were ever in a specific state (history-based).
     *
     * Requires HasStateHistory trait on the model.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function scopeWhereWasEverInState(Builder $query, string|StateContract $state, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $stateName = $this->resolveStateName($state);
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return $query->whereHas('stateHistory', function ($q) use ($stateName, $field, $historyTable) {
            $q->where("{$historyTable}.to_state", $stateName)
                ->where("{$historyTable}.field", $field);
        });
    }

    /**
     * Scope to filter by models that transitioned from one state to another (history-based).
     *
     * Requires HasStateHistory trait on the model.
     *
     * @param  class-string<StateContract>|StateContract|string  $fromState
     * @param  class-string<StateContract>|StateContract|string  $toState
     */
    public function scopeWhereTransitionedFromTo(
        Builder $query,
        string|StateContract $fromState,
        string|StateContract $toState,
        ?string $field = null
    ): Builder {
        $field = $field ?? $this->getDefaultStateField();
        $fromName = $this->resolveStateName($fromState);
        $toName = $this->resolveStateName($toState);
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return $query->whereHas('stateHistory', function ($q) use ($fromName, $toName, $field, $historyTable) {
            $q->where("{$historyTable}.from_state", $fromName)
                ->where("{$historyTable}.to_state", $toName)
                ->where("{$historyTable}.field", $field);
        });
    }

    /**
     * Scope to filter by models whose state changed after a specific date (history-based).
     *
     * Requires HasStateHistory trait on the model.
     *
     * @param  \DateTimeInterface|string  $date
     */
    public function scopeWhereStateChangedAfter(Builder $query, $date, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return $query->whereHas('stateHistory', function ($q) use ($date, $field, $historyTable) {
            $q->where("{$historyTable}.field", $field)
                ->where("{$historyTable}.created_at", '>', $date);
        });
    }

    /**
     * Scope to filter by models whose state was changed by a specific user (history-based).
     *
     * Requires HasStateHistory trait on the model.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|int  $performer
     */
    public function scopeWhereStateChangedBy(Builder $query, $performer, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        $performerId = $performer instanceof Authenticatable ? $performer->getAuthIdentifier() : $performer;

        return $query->whereHas('stateHistory', function ($q) use ($performerId, $field, $historyTable) {
            $q->where("{$historyTable}.field", $field)
                ->where("{$historyTable}.performer_id", $performerId);
        });
    }

    /**
     * Scope to filter by models with at least N transitions (history-based).
     *
     * Requires HasStateHistory trait on the model.
     */
    public function scopeWhereTransitionCountAtLeast(Builder $query, int $count, ?string $field = null): Builder
    {
        $field = $field ?? $this->getDefaultStateField();
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        return $query->whereHas('stateHistory', function ($q) use ($field, $historyTable) {
            $q->where("{$historyTable}.field", $field);
        }, '>=', $count);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Get the default state field.
     */
    protected function getDefaultStateField(): string
    {
        $configs = static::getAllStateConfigs();

        return array_key_first($configs) ?? 'state';
    }

    /**
     * Resolve state class from config.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     * @return class-string<StateContract>|null
     */
    protected function resolveStateClassFromConfig(
        string|StateContract $state,
        StateConfig $config
    ): ?string {
        // If StateContract instance
        if ($state instanceof StateContract) {
            return $state::class;
        }

        // If class exists and is subclass
        if (class_exists($state)) {
            if (is_subclass_of($state, $config->getBaseStateClass())) {
                return $state;
            }

            return null;
        }

        // Resolve by name
        return $config->resolveStateClass($state);
    }

    /**
     * Resolve state name from class or instance.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    protected function resolveStateName(string|StateContract $state): string
    {
        if ($state instanceof StateContract) {
            return $state->name();
        }

        if (class_exists($state) && is_subclass_of($state, StateContract::class)) {
            return $state::name();
        }

        return $state;
    }
}
