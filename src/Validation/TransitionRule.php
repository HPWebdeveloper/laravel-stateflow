<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Validation;

use Closure;
use Hpwebdeveloper\LaravelStateflow\HasStatesContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * Validation rule to check if a state transition is allowed.
 *
 * This rule validates that:
 * 1. The target state is a valid transition from the current state
 * 2. Optionally, the user has permission to perform the transition
 *
 * Usage:
 *   'state' => [new TransitionRule($post)]
 *   'state' => [TransitionRule::for($post)->checkPermissions()]
 *   'state' => [TransitionRule::for($post)->field('status')->allowSameState()]
 *
 * @example
 * // In a Form Request:
 * public function rules(): array
 * {
 *     return [
 *         'state' => [
 *             'required',
 *             TransitionRule::for($this->post)->checkPermissions($this->user()),
 *         ],
 *     ];
 * }
 */
class TransitionRule implements DataAwareRule, ValidationRule, ValidatorAwareRule
{
    /**
     * The data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * The validator instance.
     */
    protected ?Validator $validator = null;

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
     * Custom error message for invalid transitions.
     */
    protected ?string $customMessage = null;

    /**
     * Indicates if the rule should be run even when the attribute is empty.
     */
    public bool $implicit = true;

    /**
     * Create a new TransitionRule instance.
     *
     * @param  Model&HasStatesContract  $model  The model to validate transitions for
     * @param  string|null  $field  The state field name (optional, uses default)
     */
    public function __construct(
        protected Model $model,
        ?string $field = null,
    ) {
        $this->field = $field;
    }

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

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
            $fail('The :attribute field is required.');

            return;
        }

        // Ensure value is a string
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        // Get current state name
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
            $message = $this->customMessage
                ?? "Cannot transition from '{$currentState}' to '{$value}'.";
            $fail($message);

            return;
        }

        // Check permissions if enabled
        if ($this->checkPermissions) {
            $this->validatePermissions($value, $fail);
        }
    }

    /**
     * Validate user permissions for the transition.
     *
     * @param  string  $targetState  The target state name
     * @param  Closure  $fail  The callback to call on validation failure
     */
    protected function validatePermissions(string $targetState, Closure $fail): void
    {
        $user = $this->user ?? auth()->user();

        if ($user === null) {
            $fail('Authentication required for this transition.');

            return;
        }

        if (! $this->model->userCanTransitionTo($user, $targetState, $this->field)) {
            $fail("You don't have permission to transition to '{$targetState}'.");
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
     * Static factory for fluent usage.
     *
     * @param  Model&HasStatesContract  $model  The model to validate transitions for
     * @param  string|null  $field  The state field name (optional)
     */
    public static function for(Model $model, ?string $field = null): self
    {
        return new self($model, $field);
    }

    /**
     * Enable permission checking.
     *
     * @param  Authenticatable|null  $user  The user to check permissions for (optional, uses auth user)
     */
    public function checkPermissions(?Authenticatable $user = null): self
    {
        $this->checkPermissions = true;
        $this->user = $user;

        return $this;
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
     * Allow transitioning to the same state (no-op).
     */
    public function allowSameState(): self
    {
        $this->allowSameState = true;

        return $this;
    }

    /**
     * Set a custom error message for invalid transitions.
     *
     * @param  string  $message  The custom error message
     */
    public function withMessage(string $message): self
    {
        $this->customMessage = $message;

        return $this;
    }
}
