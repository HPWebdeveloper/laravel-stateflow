<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Validation;

use Closure;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\StateFlow;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule to check if a value is a valid state.
 *
 * This rule validates that a given value corresponds to a registered state name
 * for the specified base state class.
 *
 * Usage:
 *   'state' => [new StateRule(PostState::class)]
 *   'state' => [StateRule::for(PostState::class)]
 *   'state' => [StateRule::for(PostState::class)->nullable()]
 *
 * @example
 * // In a Form Request:
 * public function rules(): array
 * {
 *     return [
 *         'state' => ['required', StateRule::for(PostState::class)],
 *     ];
 * }
 */
class StateRule implements ValidationRule
{
    /**
     * Whether null values are allowed.
     */
    protected bool $allowNull = false;

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
     * Indicates if the rule should be run even when the attribute is empty.
     */
    public bool $implicit = true;

    /**
     * Create a new StateRule instance.
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
        // Handle null values
        if ($value === null) {
            if (! $this->allowNull) {
                $fail('The :attribute field is required.');
            }

            return;
        }

        // Ensure value is a string
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        // Reject empty strings
        if ($value === '') {
            $fail('The :attribute field is required.');

            return;
        }

        // Get all valid state names
        $validStateNames = $this->getValidStateNames();

        // Check if value is valid
        if (! in_array($value, $validStateNames, true)) {
            $fail('The :attribute must be a valid state. Valid states are: '.implode(', ', $validStateNames).'.');
        }
    }

    /**
     * Get all valid state names for this base class.
     *
     * @return array<string>
     */
    protected function getValidStateNames(): array
    {
        $states = StateFlow::getRegisteredStates($this->baseStateClass);

        $stateNames = array_map(fn ($stateClass) => $stateClass::name(), $states);

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
     * Static factory for fluent usage.
     *
     * @param  class-string<StateContract>  $baseStateClass
     */
    public static function for(string $baseStateClass): self
    {
        return new self($baseStateClass);
    }

    /**
     * Allow null values.
     */
    public function nullable(): self
    {
        $this->allowNull = true;

        return $this;
    }

    /**
     * Only allow specific states.
     *
     * @param  array<string>  $states  State names to allow
     */
    public function only(array $states): self
    {
        $this->only = $states;

        return $this;
    }

    /**
     * Exclude specific states.
     *
     * @param  array<string>  $states  State names to exclude
     */
    public function except(array $states): self
    {
        $this->except = $states;

        return $this;
    }
}
