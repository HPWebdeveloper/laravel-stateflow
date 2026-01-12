<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Validation\Rules;

use Closure;
use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Invokable validation rule for valid transitions.
 *
 * This rule provides a simpler alternative to TransitionRule when
 * you don't need access to the full validation data or validator.
 *
 * Usage:
 *   'state' => [new ValidTransition($post)]
 *   'state' => [(new ValidTransition($post))->withPermissions()]
 *   'state' => [(new ValidTransition($post))->allowSameState()]
 *
 * @example
 * // Basic usage
 * 'state' => [new ValidTransition($post)]
 *
 * // With permission checking
 * 'state' => [(new ValidTransition($post))->withPermissions($request->user())]
 */
class ValidTransition implements ValidationRule
{
    /**
     * The state field name (null for default field).
     */
    protected ?string $field = null;

    /**
     * Whether to check user permissions.
     */
    protected bool $checkPermissions = false;

    /**
     * The user to check permissions for.
     */
    protected ?Authenticatable $user = null;

    /**
     * Whether to allow transitioning to the same state.
     */
    protected bool $allowSameState = false;

    /**
     * Indicates if the rule should be run even when the attribute is empty.
     */
    public bool $implicit = true;

    /**
     * Create a new ValidTransition instance.
     *
     * @param  Model&HasStatesContract  $model  The model to validate transitions for
     */
    public function __construct(
        protected Model $model,
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

        $currentState = $this->getCurrentStateName();

        // Check if transitioning to same state
        if ($currentState === $value) {
            if (! $this->allowSameState) {
                $fail("The :attribute is already {$value}.");
            }

            return;
        }

        // Check if transition is allowed
        if (! $this->model->canTransitionTo($value, $this->field)) {
            $fail("Cannot transition from '{$currentState}' to '{$value}'.");

            return;
        }

        // Check permissions if enabled
        if ($this->checkPermissions) {
            $user = $this->user ?? auth()->user();

            if ($user === null) {
                $fail('Authentication required for this transition.');

                return;
            }

            if (! $this->model->userCanTransitionTo($user, $value, $this->field)) {
                $fail("You don't have permission to transition to '{$value}'.");
            }
        }
    }

    /**
     * Get the current state name.
     */
    protected function getCurrentStateName(): string
    {
        $field = $this->field ?? $this->getDefaultField();
        $currentState = $this->model->{$field};

        if ($currentState === null) {
            return '';
        }

        return is_object($currentState) ? $currentState->name() : (string) $currentState;
    }

    /**
     * Get the default state field from the model's state configs.
     */
    protected function getDefaultField(): string
    {
        $modelClass = get_class($this->model);
        $configs = $modelClass::getAllStateConfigs();

        return array_key_first($configs) ?? 'state';
    }

    /**
     * Set the state field.
     *
     * @param  string  $field  The state field name
     */
    public function field(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Enable permission checking.
     *
     * @param  Authenticatable|null  $user  The user to check permissions for (optional)
     */
    public function withPermissions(?Authenticatable $user = null): self
    {
        $this->checkPermissions = true;
        $this->user = $user;

        return $this;
    }

    /**
     * Allow transitioning to the same state (no-op).
     */
    public function allowSameState(): self
    {
        $this->allowSameState = true;

        return $this;
    }
}
