<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Exceptions;

use Exception;

/**
 * Exception thrown when there's a state configuration error.
 */
class StateConfigurationException extends Exception
{
    /**
     * Create exception for missing state configuration.
     */
    public static function missingConfiguration(string $model, string $issue): self
    {
        return new self(
            "State configuration error on model '{$model}': {$issue}"
        );
    }

    /**
     * Create exception for invalid state class.
     */
    public static function invalidStateClass(string $class): self
    {
        return new self(
            "Class '{$class}' is not a valid state class. ".
            'It must implement StateContract.'
        );
    }

    /**
     * Create exception for class not found.
     */
    public static function classNotFound(string $class): self
    {
        return new self("State class '{$class}' does not exist.");
    }

    /**
     * Create exception for class not being a subclass.
     */
    public static function notSubclass(string $class, string $baseClass): self
    {
        return new self(
            "State class '{$class}' must be a subclass of '{$baseClass}'."
        );
    }

    /**
     * Create exception for missing state configuration on field.
     */
    public static function noStateConfig(string $model, string $field): self
    {
        return new self(
            "No state configuration found for field '{$field}' on model '{$model}'. ".
            'Make sure to call registerStates() in your model.'
        );
    }

    /**
     * Create exception for missing default state.
     */
    public static function missingDefaultState(string $baseClass): self
    {
        return new self(
            "No default state defined for '{$baseClass}'. ".
            'Define a default using ->default() or #[DefaultState] attribute.'
        );
    }

    /**
     * Create exception for invalid transition format.
     */
    public static function invalidTransitionFormat(): self
    {
        return new self(
            "Invalid transition format. Each transition must be an array with 'from' and 'to' keys. ".
            "Example: ['from' => Pending::class, 'to' => Processing::class]"
        );
    }
}
