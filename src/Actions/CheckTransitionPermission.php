<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Actions;

use Hpwebdeveloper\LaravelStateflow\Actions\Concerns\AsAction;
use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Permissions\CompositeChecker;
use Hpwebdeveloper\LaravelStateflow\Permissions\PermissionDenied;
use Hpwebdeveloper\LaravelStateflow\Permissions\PolicyBasedChecker;
use Hpwebdeveloper\LaravelStateflow\Permissions\RoleBasedChecker;
use Hpwebdeveloper\LaravelStateflow\StateFlow;

/**
 * Action to check transition permissions.
 *
 * Determines if a user is allowed to perform a state transition
 * based on configured permission checkers.
 *
 * @example
 * $result = CheckTransitionPermission::run($transitionData);
 * if (!$result->allowed) {
 *     $denial = $result->denial;
 *     // Handle denied - $denial contains reason
 * }
 */
class CheckTransitionPermission
{
    use AsAction;

    /**
     * Check if transition is permitted.
     *
     * @return object{allowed: bool, denial: ?PermissionDenied}
     */
    public function handle(TransitionData $data): object
    {
        // If no performer, allow (system transition)
        if (! $data->performer) {
            return (object) ['allowed' => true, 'denial' => null];
        }

        // Check if permissions are enabled
        if (! StateFlow::checksPermissions()) {
            return (object) ['allowed' => true, 'denial' => null];
        }

        // Get permission checker
        $checker = $this->getChecker();

        // Check permission
        $allowed = $checker->canTransition(
            $data->model,
            $data->fromState,
            $data->toState,
            $data->performer
        );

        if ($allowed) {
            return (object) ['allowed' => true, 'denial' => null];
        }

        // Get denial reason
        $reason = $checker->getDenialReason(
            $data->model,
            $data->fromState,
            $data->toState,
            $data->performer
        );

        $denial = PermissionDenied::make(
            user: $data->performer,
            model: $data->model,
            field: $data->field,
            fromState: $data->fromState,
            toState: $data->toState,
            reason: $reason ?? 'Permission denied',
            checkerClass: get_class($checker)
        );

        return (object) ['allowed' => false, 'denial' => $denial];
    }

    /**
     * Get the configured permission checker.
     */
    protected function getChecker(): PermissionChecker
    {
        // Check if custom checker is bound in container
        if (app()->bound(PermissionChecker::class)) {
            return app(PermissionChecker::class);
        }

        // Build checker based on config
        return $this->buildDefaultChecker();
    }

    /**
     * Build the default permission checker from config.
     */
    protected function buildDefaultChecker(): PermissionChecker
    {
        $checkers = [];

        // Role-based checker (if enabled)
        if (config('laravel-stateflow.permissions.role_based', true)) {
            $checkers[] = new RoleBasedChecker(
                config('laravel-stateflow.permissions.user_role_attribute', 'role')
            );
        }

        // Policy-based checker (if enabled)
        if (config('laravel-stateflow.permissions.policy_based', false)) {
            $checkers[] = new PolicyBasedChecker(
                config('laravel-stateflow.permissions.policy_ability_prefix', 'transitionTo')
            );
        }

        // If no checkers configured, use role-based by default
        if (empty($checkers)) {
            return new RoleBasedChecker;
        }

        // Single checker
        if (count($checkers) === 1) {
            return $checkers[0];
        }

        // Multiple checkers - all must pass
        return CompositeChecker::all($checkers);
    }

    /**
     * Quick check method for convenience.
     */
    public static function check(TransitionData $data): bool
    {
        return static::run($data)->allowed;
    }

    /**
     * Get denial details for a transition.
     */
    public static function getDenial(TransitionData $data): ?PermissionDenied
    {
        return static::run($data)->denial;
    }
}
