<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Services;

use Hpwebdeveloper\LaravelStateflow\Contracts\PermissionChecker;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Default permission checker that allows all transitions.
 *
 * Used when no custom permission checker is configured.
 * For actual permission checking, use RoleBasedChecker or PolicyBasedChecker.
 */
class DefaultPermissionChecker implements PermissionChecker
{
    public function canTransition(
        Model $model,
        string $fromState,
        string $toState,
        ?Authenticatable $user = null
    ): bool {
        return true;
    }

    public function getUserRole(?Authenticatable $user): string|array|null
    {
        return null;
    }

    public function getDenialReason(
        Model $model,
        string $fromState,
        string $toState,
        ?Authenticatable $user = null
    ): ?string {
        return null;
    }
}
