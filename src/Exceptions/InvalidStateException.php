<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Exceptions;

use Exception;

/**
 * Exception thrown when an invalid state is encountered.
 */
class InvalidStateException extends Exception
{
    /**
     * Create exception for unknown state.
     */
    public static function unknownState(string $state, string $baseClass): self
    {
        return new self(
            "Unknown state '{$state}' for base class '{$baseClass}'. ".
            'Make sure the state class exists and is registered.'
        );
    }

    /**
     * Create exception for invalid state.
     */
    public static function invalidState(string $state, string $baseClass): self
    {
        return new self(
            "Invalid state '{$state}' for base class '{$baseClass}'. ".
            'The state must be a valid registered state class or name.'
        );
    }

    /**
     * Create exception for invalid value type.
     */
    public static function invalidValue(mixed $value): self
    {
        $type = is_object($value) ? get_class($value) : gettype($value);

        return new self(
            "Invalid state value of type '{$type}'. ".
            'State must be a string or StateContract instance.'
        );
    }
}
