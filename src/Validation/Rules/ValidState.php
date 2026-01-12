<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Validation\Rules;

use Closure;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Invokable validation rule for valid states with filtering options.
 *
 * This rule provides a cleaner syntax for validating states with
 * additional filtering capabilities using only() and except() methods.
 *
 * Usage:
 *   'state' => [new ValidState(PostState::class)]
 *   'state' => [(new ValidState(PostState::class))->only(['draft', 'review'])]
 *   'state' => [(new ValidState(PostState::class))->except(['archived'])]
 *
 * @example
 * // Only allow certain states
 * 'state' => [(new ValidState(PostState::class))->only(['draft', 'review'])]
 *
 * // Exclude certain states
 * 'state' => [(new ValidState(PostState::class))->except(['archived', 'deleted'])]
 */
class ValidState implements ValidationRule
{
    /**
     * Optional list of allowed states (whitelist).
     *
     * @var array<string>
     */
    protected array $only = [];

    /**
     * Optional list of excluded states (blacklist).
     *
     * @var array<string>
     */
    protected array $except = [];

    /**
     * Whether to show valid states in the error message.
     */
    protected bool $showValidStates = true;

    /**
     * Indicates if the rule should be run even when the attribute is empty.
     */
    public bool $implicit = true;

    /**
     * Create a new ValidState instance.
     *
     * @param  class-string<StateContract>  $baseStateClass  The base state class to validate against
     */
    public function __construct(
        protected string $baseStateClass,
    ) {}

    /**
     * Validate the attribute.
     *
     * @param  string  $attribute  The name of the attribute being validated
     * @param  mixed  $value  The value being validated
     * @param  Closure  $fail  The callback to call on validation failure
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Ensure value is a string
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $validStates = $this->getValidStates();

        if (! in_array($value, $validStates, true)) {
            if ($this->showValidStates && ! empty($validStates)) {
                $fail('The :attribute must be one of: '.implode(', ', $validStates).'.');
            } else {
                $fail('The :attribute is not a valid state.');
            }
        }
    }

    /**
     * Get valid states based on filters.
     *
     * @return array<string>
     */
    protected function getValidStates(): array
    {
        $states = StateFlow::getRegisteredStates($this->baseStateClass);
        $stateNames = array_map(fn ($class) => $class::name(), $states);

        // Apply whitelist filter
        if (! empty($this->only)) {
            $stateNames = array_intersect($stateNames, $this->only);
        }

        // Apply blacklist filter
        if (! empty($this->except)) {
            $stateNames = array_diff($stateNames, $this->except);
        }

        return array_values($stateNames);
    }

    /**
     * Only allow these states.
     *
     * @param  array<string>  $states  State names to allow
     */
    public function only(array $states): self
    {
        $this->only = $states;

        return $this;
    }

    /**
     * Exclude these states.
     *
     * @param  array<string>  $states  State names to exclude
     */
    public function except(array $states): self
    {
        $this->except = $states;

        return $this;
    }

    /**
     * Hide valid states from the error message.
     */
    public function hideValidStates(): self
    {
        $this->showValidStates = false;

        return $this;
    }
}
