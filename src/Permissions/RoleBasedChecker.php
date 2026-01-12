<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Permissions;

use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission checker based on user roles.
 *
 * Checks if user's role is in the state's permitted roles.
 * Supports both string roles and enum roles.
 *
 * @example
 * $checker = new RoleBasedChecker('role');
 * $allowed = $checker->canTransition($user, $model, 'state', Draft::class, Published::class);
 */
class RoleBasedChecker implements PermissionChecker
{
    /**
     * The attribute name for the user's role.
     */
    protected string $roleAttribute;

    /**
     * Create a new RoleBasedChecker instance.
     */
    public function __construct(?string $roleAttribute = null)
    {
        $this->roleAttribute = $roleAttribute ?? config('laravel-stateflow.permissions.user_role_attribute', 'role');
    }

    /**
     * Check if user can transition model to target state.
     *
     * @param  Model  $model  The model being transitioned
     * @param  class-string<StateContract>  $fromState  Current state class
     * @param  class-string<StateContract>  $toState  Target state class
     * @param  Authenticatable|null  $user  User attempting transition (null = current auth user)
     */
    public function canTransition(
        Model $model,
        string $fromState,
        string $toState,
        ?Authenticatable $user = null
    ): bool {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        // Get permitted roles from target state
        if (! class_exists($toState) || ! method_exists($toState, 'permittedRoles')) {
            return true;
        }

        $permittedRoles = $toState::permittedRoles();

        // If no roles specified, anyone can transition
        if (empty($permittedRoles)) {
            return true;
        }

        // Get user's role
        $userRole = $this->getUserRole($user);

        if ($userRole === null) {
            return false;
        }

        // Handle array of roles
        if (is_array($userRole)) {
            foreach ($userRole as $role) {
                if (in_array($role, $permittedRoles, true)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($userRole, $permittedRoles, true);
    }

    /**
     * Get the user's role for permission checking.
     *
     * @return string|array<string>|null
     */
    public function getUserRole(?Authenticatable $user): string|array|null
    {
        if (! $user) {
            return null;
        }

        $role = $user->{$this->roleAttribute} ?? null;

        if ($role === null) {
            return null;
        }

        // Handle enum roles
        if (is_object($role) && enum_exists(get_class($role))) {
            /** @var \BackedEnum $role */
            return $role->value;
        }

        // Handle array roles
        if (is_array($role)) {
            return array_map(function ($r) {
                if (is_object($r) && enum_exists(get_class($r))) {
                    /** @var \BackedEnum $r */
                    return $r->value;
                }

                return (string) $r;
            }, $role);
        }

        return (string) $role;
    }

    /**
     * Get the reason for denial.
     */
    public function getDenialReason(
        Model $model,
        string $fromState,
        string $toState,
        ?Authenticatable $user = null
    ): ?string {
        if ($this->canTransition($model, $fromState, $toState, $user)) {
            return null;
        }

        $user ??= auth()->user();
        $userRole = $user ? ($this->getUserRole($user) ?? 'none') : 'unauthenticated';

        if (is_array($userRole)) {
            $userRole = implode(', ', $userRole);
        }

        if (! class_exists($toState) || ! method_exists($toState, 'permittedRoles')) {
            return 'Target state does not define permitted roles.';
        }

        $permittedRoles = implode(', ', $toState::permittedRoles());

        return "User role '{$userRole}' is not permitted. Allowed roles: {$permittedRoles}";
    }
}
