<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException;

/**
 * Configuration for a state field on a model.
 *
 * Defines the base state class, default state, and transition mappings.
 *
 * @example
 * StateConfig::make(PostState::class)
 *     ->default(Draft::class)
 *     ->allowTransition(Draft::class, Review::class)
 *     ->allowTransition(Review::class, Published::class, PublishPost::class)
 */
class StateConfig
{
    /**
     * Base state class for this configuration.
     *
     * @var class-string<StateContract>
     */
    protected string $baseStateClass;

    /**
     * Default state class.
     *
     * @var class-string<StateContract>|null
     */
    protected ?string $defaultStateClass = null;

    /**
     * Registered state classes.
     *
     * @var array<class-string<StateContract>>
     */
    protected array $states = [];

    /**
     * Custom transition mappings.
     *
     * @var array<string, array<string, class-string|null>>
     */
    protected array $transitions = [];

    /**
     * State field name on model.
     */
    protected string $field = 'state';

    /**
     * Create a new StateConfig instance.
     *
     * @param  class-string<StateContract>  $baseStateClass
     */
    public function __construct(string $baseStateClass)
    {
        $this->baseStateClass = $baseStateClass;
    }

    /**
     * Static factory for fluent configuration.
     *
     * @param  class-string<StateContract>  $baseStateClass
     */
    public static function make(string $baseStateClass): self
    {
        return new self($baseStateClass);
    }

    /**
     * Set the default state.
     *
     * @param  class-string<StateContract>  $stateClass
     */
    public function default(string $stateClass): self
    {
        $this->validateStateClass($stateClass);
        $this->defaultStateClass = $stateClass;

        // Also register this state
        $this->registerState($stateClass);

        return $this;
    }

    /**
     * Set the field name.
     */
    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Register a state class.
     *
     * @param  class-string<StateContract>  $stateClass
     */
    public function registerState(string $stateClass): self
    {
        $this->validateStateClass($stateClass);

        if (! in_array($stateClass, $this->states, true)) {
            $this->states[] = $stateClass;
        }

        return $this;
    }

    /**
     * Register multiple state classes.
     *
     * @param  array<class-string<StateContract>>  $stateClasses
     */
    public function registerStates(array $stateClasses): self
    {
        foreach ($stateClasses as $stateClass) {
            $this->registerState($stateClass);
        }

        return $this;
    }

    /**
     * Define an allowed transition.
     *
     * @param  class-string<StateContract>  $from
     * @param  class-string<StateContract>  $to
     * @param  class-string|null  $transitionClass  Custom transition action
     */
    public function allowTransition(
        string $from,
        string $to,
        ?string $transitionClass = null
    ): self {
        $this->validateStateClass($from);
        $this->validateStateClass($to);

        if (! isset($this->transitions[$from])) {
            $this->transitions[$from] = [];
        }

        $this->transitions[$from][$to] = $transitionClass;

        // Also register both states
        $this->registerState($from);
        $this->registerState($to);

        return $this;
    }

    /**
     * Define multiple allowed transitions from one state.
     *
     * @param  class-string<StateContract>  $from
     * @param  array<class-string<StateContract>>  $toStates
     */
    public function allowTransitions(string $from, array $toStates): self
    {
        foreach ($toStates as $to) {
            $this->allowTransition($from, $to);
        }

        return $this;
    }

    /**
     * Define multiple allowed transitions from an array of transition definitions.
     *
     * This method is useful when using enums to define workflow topology.
     * Each transition should be an array with 'from' and 'to' keys.
     *
     * @param  array<array{from: class-string<StateContract>, to: class-string<StateContract>}>  $transitions
     *
     * @example
     * // From an enum that returns transitions
     * StateConfig::make(OrderState::class)
     *     ->allowTransitionsFromArray(OrderStatus::transitions());
     *
     * // Or with explicit array
     * StateConfig::make(OrderState::class)
     *     ->allowTransitionsFromArray([
     *         ['from' => Pending::class, 'to' => Processing::class],
     *         ['from' => Processing::class, 'to' => Shipped::class],
     *     ]);
     */
    public function allowTransitionsFromArray(array $transitions): self
    {
        foreach ($transitions as $transition) {
            if (! isset($transition['from'], $transition['to'])) {
                throw StateConfigurationException::invalidTransitionFormat();
            }

            $this->allowTransition($transition['from'], $transition['to']);
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    /**
     * Get the base state class.
     *
     * @return class-string<StateContract>
     */
    public function getBaseStateClass(): string
    {
        return $this->baseStateClass;
    }

    /**
     * Get the default state class.
     *
     * @return class-string<StateContract>|null
     */
    public function getDefaultStateClass(): ?string
    {
        // 1. Explicit default
        if ($this->defaultStateClass) {
            return $this->defaultStateClass;
        }

        // 2. Find state with isDefault() true
        foreach ($this->states as $stateClass) {
            if (method_exists($stateClass, 'isDefault') && $stateClass::isDefault()) {
                return $stateClass;
            }
        }

        // 3. First registered state
        return $this->states[0] ?? null;
    }

    /**
     * Get the field name.
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Get all registered states.
     *
     * @return array<class-string<StateContract>>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * Get allowed transitions from a state.
     *
     * @param  class-string<StateContract>  $fromState
     * @return array<class-string<StateContract>>
     */
    public function getAllowedTransitions(string $fromState): array
    {
        // 1. Check config-defined transitions
        if (isset($this->transitions[$fromState])) {
            /** @var array<class-string<StateContract>> $transitions */
            $transitions = array_keys($this->transitions[$fromState]);

            return $transitions;
        }

        // 2. Fall back to state class definition
        if (method_exists($fromState, 'allowedTransitions')) {
            return $fromState::allowedTransitions();
        }

        return [];
    }

    /**
     * Check if a transition is allowed.
     *
     * @param  class-string<StateContract>  $from
     * @param  class-string<StateContract>  $to
     */
    public function isTransitionAllowed(string $from, string $to): bool
    {
        return in_array($to, $this->getAllowedTransitions($from), true);
    }

    /**
     * Get custom transition class for a transition.
     *
     * @param  class-string<StateContract>  $from
     * @param  class-string<StateContract>  $to
     * @return class-string|null
     */
    public function getTransitionClass(string $from, string $to): ?string
    {
        // 1. Check config-defined transitions
        if (isset($this->transitions[$from][$to])) {
            /** @var class-string|null $transitionClass */
            $transitionClass = $this->transitions[$from][$to];

            return $transitionClass;
        }

        // 2. Check state class definition
        if (method_exists($from, 'getTransitionClass')) {
            /** @var class-string|null $transitionClass */
            $transitionClass = $from::getTransitionClass($to);

            return $transitionClass;
        }

        return null;
    }

    /**
     * Resolve a state class from name or class.
     *
     * @return class-string<StateContract>|null
     */
    public function resolveStateClass(string $state): ?string
    {
        // Already a class
        if (class_exists($state) && is_subclass_of($state, $this->baseStateClass)) {
            return $state;
        }

        // Find by name
        foreach ($this->states as $stateClass) {
            if ($stateClass::name() === $state) {
                return $stateClass;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate a state class.
     *
     * @param  class-string  $stateClass
     *
     * @throws StateConfigurationException
     */
    protected function validateStateClass(string $stateClass): void
    {
        if (! class_exists($stateClass)) {
            throw StateConfigurationException::classNotFound($stateClass);
        }

        if (! is_subclass_of($stateClass, $this->baseStateClass)) {
            throw StateConfigurationException::notSubclass(
                $stateClass,
                $this->baseStateClass
            );
        }
    }
}
