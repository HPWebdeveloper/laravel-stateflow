<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Permissions;

use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Combines multiple permission checkers.
 *
 * All checkers must pass (AND logic) or any checker must pass (OR logic).
 *
 * @example
 * // All checkers must pass
 * $checker = CompositeChecker::all([
 *     new RoleBasedChecker(),
 *     new PolicyBasedChecker(),
 * ]);
 *
 * // Any checker must pass
 * $checker = CompositeChecker::any([
 *     new RoleBasedChecker(),
 *     new PolicyBasedChecker(),
 * ]);
 */
class CompositeChecker implements PermissionChecker
{
    /**
     * @var array<PermissionChecker>
     */
    protected array $checkers;

    /**
     * Whether all checkers must pass (AND) or any (OR).
     */
    protected bool $requireAll;

    /**
     * Create a new CompositeChecker instance.
     *
     * @param  array<PermissionChecker>  $checkers
     */
    public function __construct(array $checkers, bool $requireAll = true)
    {
        $this->checkers = $checkers;
        $this->requireAll = $requireAll;
    }

    /**
     * Create a checker where ALL must pass (AND logic).
     *
     * @param  array<PermissionChecker>  $checkers
     */
    public static function all(array $checkers): self
    {
        return new self($checkers, true);
    }

    /**
     * Create a checker where ANY must pass (OR logic).
     *
     * @param  array<PermissionChecker>  $checkers
     */
    public static function any(array $checkers): self
    {
        return new self($checkers, false);
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
        if (empty($this->checkers)) {
            return true;
        }

        foreach ($this->checkers as $checker) {
            $result = $checker->canTransition($model, $fromState, $toState, $user);

            // AND logic: if any checker fails, deny
            if ($this->requireAll && ! $result) {
                return false;
            }

            // OR logic: if any checker passes, allow
            if (! $this->requireAll && $result) {
                return true;
            }
        }

        // AND logic: all passed, OR logic: none passed
        return $this->requireAll;
    }

    /**
     * Get the user's role for permission checking.
     *
     * Returns role from first checker that has one.
     *
     * @return string|array<string>|null
     */
    public function getUserRole(?Authenticatable $user): string|array|null
    {
        foreach ($this->checkers as $checker) {
            $role = $checker->getUserRole($user);
            if ($role !== null) {
                return $role;
            }
        }

        return null;
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

        $reasons = [];

        foreach ($this->checkers as $checker) {
            $reason = $checker->getDenialReason($model, $fromState, $toState, $user);
            if ($reason) {
                $reasons[] = $reason;
            }
        }

        return implode('; ', $reasons) ?: 'Permission denied.';
    }

    /**
     * Add a checker to the composite.
     */
    public function add(PermissionChecker $checker): self
    {
        $this->checkers[] = $checker;

        return $this;
    }

    /**
     * Get all registered checkers.
     *
     * @return array<PermissionChecker>
     */
    public function getCheckers(): array
    {
        return $this->checkers;
    }

    /**
     * Check if using AND logic.
     */
    public function isRequireAll(): bool
    {
        return $this->requireAll;
    }
}
