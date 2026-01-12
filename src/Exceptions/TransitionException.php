<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Exceptions;

use Exception;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;

/**
 * Exception for transition-related errors.
 *
 * Provides factory methods for common transition failure scenarios.
 */
class TransitionException extends Exception
{
    protected ?TransitionContext $context = null;

    /**
     * Create exception for disallowed transition.
     */
    public static function notAllowed(string $from, string $to, string $model): self
    {
        return new self(
            "Transition from '{$from}' to '{$to}' is not allowed on {$model}."
        );
    }

    /**
     * Create exception for unauthorized transition.
     */
    public static function unauthorized(string $to, string $role): self
    {
        return new self(
            "Role '{$role}' is not authorized to transition to '{$to}'."
        );
    }

    /**
     * Create exception when transition is aborted by a hook.
     */
    public static function abortedByHook(string $hookName): self
    {
        return new self(
            "Transition was aborted by '{$hookName}' hook."
        );
    }

    /**
     * Create exception when transition is cancelled by an event listener.
     */
    public static function cancelledByEvent(string $reason): self
    {
        return new self(
            "Transition was cancelled by event listener: {$reason}"
        );
    }

    /**
     * Create exception for validation failures.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function validationFailed(array $errors): self
    {
        $message = implode(', ', array_map(
            fn ($field, $msgs) => "{$field}: ".implode(', ', (array) $msgs),
            array_keys($errors),
            $errors
        ));

        return new self("Transition validation failed: {$message}");
    }

    /**
     * Create exception for action failures.
     */
    public static function actionFailed(string $action, string $message): self
    {
        return new self("Transition action '{$action}' failed: {$message}");
    }

    /**
     * Create exception for missing state configuration.
     */
    public static function missingConfiguration(string $field): self
    {
        return new self("No state configuration found for field '{$field}'.");
    }

    /**
     * Create exception for null current state.
     */
    public static function nullState(string $field): self
    {
        return new self("Current state is null for field '{$field}'.");
    }

    /**
     * Create exception for unknown state.
     */
    public static function unknownState(string $state): self
    {
        return new self("Unknown state: {$state}");
    }

    /**
     * Attach context to exception.
     */
    public function withContext(TransitionContext $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get attached context.
     */
    public function getContext(): ?TransitionContext
    {
        return $this->context;
    }
}
