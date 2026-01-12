<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for permission checking.
 *
 * Implement this interface to customize how transition
 * permissions are verified.
 */
interface PermissionChecker
{
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
    ): bool;

    /**
     * Get the user's role for permission checking.
     *
     * @return string|array<string>|null
     */
    public function getUserRole(?Authenticatable $user): string|array|null;

    /**
     * Get the reason for denial if permission is denied.
     *
     * @param  Model  $model  The model being transitioned
     * @param  class-string<StateContract>  $fromState  Current state class
     * @param  class-string<StateContract>  $toState  Target state class
     * @param  Authenticatable|null  $user  User attempting transition (null = current auth user)
     */
    public function getDenialReason(
        Model $model,
        string $fromState,
        string $toState,
        ?Authenticatable $user = null
    ): ?string;
}
