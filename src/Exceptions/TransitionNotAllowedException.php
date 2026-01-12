<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Exceptions;

use Exception;

/**
 * Exception thrown when a transition is not allowed.
 */
class TransitionNotAllowedException extends Exception
{
    /**
     * Create exception for transition not allowed.
     */
    public static function create(string $fromState, string $toState): self
    {
        return new self(
            "Transition from '{$fromState}' to '{$toState}' is not allowed."
        );
    }

    /**
     * Create exception for transition not in allowed list.
     */
    public static function notInAllowedTransitions(
        string $fromState,
        string $toState,
        string $modelClass
    ): self {
        return new self(
            "Transition from '{$fromState}' to '{$toState}' is not allowed ".
            "on model '{$modelClass}'."
        );
    }

    /**
     * Create exception for insufficient permissions.
     */
    public static function insufficientPermission(
        string $toState,
        string $role
    ): self {
        return new self(
            "Role '{$role}' does not have permission to transition to state '{$toState}'."
        );
    }
}
