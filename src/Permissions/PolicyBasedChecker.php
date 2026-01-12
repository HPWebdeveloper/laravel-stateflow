<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Permissions;

use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Permission checker based on Laravel policies.
 *
 * Uses Laravel's authorization system (Gate/Policy).
 * The ability name is generated from the state name: "transitionTo{StateName}"
 *
 * @example
 * // Define a gate or policy method:
 * Gate::define('transitionToPublished', function ($user, $post) {
 *     return $user->role === 'admin';
 * });
 *
 * $checker = new PolicyBasedChecker();
 * $allowed = $checker->canTransition($user, $model, 'state', Draft::class, Published::class);
 */
class PolicyBasedChecker implements PermissionChecker
{
    /**
     * Prefix for the ability name.
     */
    protected string $abilityPrefix;

    /**
     * Create a new PolicyBasedChecker instance.
     */
    public function __construct(?string $abilityPrefix = null)
    {
        $this->abilityPrefix = $abilityPrefix ?? config('laravel-stateflow.permissions.policy_ability_prefix', 'transitionTo');
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

        $ability = $this->getAbilityName($toState);

        // Check if policy defines this ability
        if (! Gate::has($ability) && ! $this->policyDefinesAbility($model, $ability)) {
            // If no policy defined, allow (fall through to other checkers)
            return true;
        }

        return Gate::forUser($user)->allows($ability, $model);
    }

    /**
     * Get the user's role for permission checking.
     *
     * Policy-based checker doesn't use roles directly.
     *
     * @return string|array<string>|null
     */
    public function getUserRole(?Authenticatable $user): string|array|null
    {
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

        $ability = $this->getAbilityName($toState);

        return "Policy denies '{$ability}' ability on this model.";
    }

    /**
     * Get the ability name for a state.
     *
     * @param  class-string<StateContract>|string  $state
     */
    protected function getAbilityName(string $state): string
    {
        $stateName = class_exists($state) && method_exists($state, 'name')
            ? $state::name()
            : $state;

        return $this->abilityPrefix.ucfirst($stateName);
    }

    /**
     * Check if model's policy defines the ability.
     */
    protected function policyDefinesAbility(Model $model, string $ability): bool
    {
        $policy = Gate::getPolicyFor($model);

        if (! $policy) {
            return false;
        }

        return method_exists($policy, $ability);
    }
}
