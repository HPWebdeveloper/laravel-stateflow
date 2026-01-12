<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Actions;

use Hpwebdeveloper\LaravelStateflow\Actions\Concerns\AsAction;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\StateFlow;

/**
 * Action to validate if a transition can occur.
 *
 * Use this to check before attempting a transition.
 *
 * @example
 * $validation = ValidateTransition::run($transitionData);
 * if (!$validation['valid']) {
 *     // Handle reasons
 * }
 */
class ValidateTransition
{
    use AsAction;

    /**
     * Validate if transition is allowed.
     *
     * @return array{valid: bool, reasons: array<string>}
     */
    public function handle(TransitionData $data): array
    {
        $reasons = [];

        // 1. Check if transition is in allowed list
        if (! $this->isTransitionAllowed($data)) {
            $fromName = $this->resolveStateName($data->fromState);
            $toName = $this->resolveStateName($data->toState);
            $reasons[] = "Transition from '{$fromName}' to '{$toName}' is not allowed.";
        }

        // 2. Check permissions
        if (StateFlow::hasFeature('permissions')) {
            if (! $this->hasPermission($data)) {
                $toName = $this->resolveStateName($data->toState);
                $reasons[] = "User does not have permission to transition to '{$toName}'.";
            }
        }

        // 3. Check custom validation (if model implements it)
        $customReasons = $this->runCustomValidation($data);
        $reasons = array_merge($reasons, $customReasons);

        return [
            'valid' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Check if transition is in allowed transitions list.
     */
    protected function isTransitionAllowed(TransitionData $data): bool
    {
        $model = $data->model;

        if (! method_exists($model, 'canTransitionTo')) {
            return true;
        }

        return $model->canTransitionTo($data->toState, $data->field);
    }

    /**
     * Check if user has permission for transition.
     */
    protected function hasPermission(TransitionData $data): bool
    {
        if (! $data->performer) {
            // Allow if no performer (system transition)
            return true;
        }

        $toStateClass = $data->toState;
        if (! class_exists($toStateClass)) {
            return true;
        }

        if (! method_exists($toStateClass, 'permittedRoles')) {
            return true;
        }

        $permittedRoles = $toStateClass::permittedRoles();

        if (empty($permittedRoles)) {
            return true;
        }

        $userRole = $this->extractUserRole($data);

        if ($userRole === null) {
            return false;
        }

        return in_array($userRole, $permittedRoles, true);
    }

    /**
     * Run custom model validation.
     *
     * @return array<string>
     */
    protected function runCustomValidation(TransitionData $data): array
    {
        $model = $data->model;
        $reasons = [];

        // Check for custom validator method on model
        $toStateName = $this->resolveStateName($data->toState);
        $methodName = 'validateTransitionTo'.ucfirst($toStateName);

        if (method_exists($model, $methodName)) {
            $result = $model->{$methodName}($data);
            if ($result !== true) {
                $reasons[] = is_string($result) ? $result : 'Custom validation failed.';
            }
        }

        // Also check generic validateTransition method
        if (method_exists($model, 'validateTransition')) {
            $result = $model->validateTransition($data);
            if ($result !== true) {
                $reasons[] = is_string($result) ? $result : 'Model validation failed.';
            }
        }

        return $reasons;
    }

    /**
     * Resolve state name from class or string.
     */
    protected function resolveStateName(string $state): string
    {
        if (class_exists($state) && method_exists($state, 'name')) {
            return $state::name();
        }

        return $state;
    }

    /**
     * Extract user role from transition data.
     */
    protected function extractUserRole(TransitionData $data): ?string
    {
        if (! $data->performer) {
            return null;
        }

        $role = null;

        // Try role property
        if (property_exists($data->performer, 'role')) {
            $role = $data->performer->role;
        }

        // Try getRole method
        if ($role === null && method_exists($data->performer, 'getRole')) {
            $role = $data->performer->getRole();
        }

        // Try getAttribute (Eloquent)
        if ($role === null && method_exists($data->performer, 'getAttribute')) {
            $role = $data->performer->getAttribute('role');
        }

        if ($role === null) {
            return null;
        }

        // Handle enum roles
        if (is_object($role) && enum_exists(get_class($role))) {
            return $role->value;
        }

        return (string) $role;
    }
}
