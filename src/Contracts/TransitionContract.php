<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Contracts;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;

/**
 * Contract for custom transition classes.
 *
 * Implement this interface to create transitions with
 * custom logic, side effects, or validation.
 */
interface TransitionContract
{
    /**
     * Check if this transition can be executed.
     *
     * Called before handle() to validate business rules.
     */
    public function canTransition(): bool;

    /**
     * Execute the transition logic.
     *
     * This is where side effects (notifications, logging, etc.) happen.
     * The state change itself is handled by StateFlow.
     */
    public function handle(): TransitionResult;

    /**
     * Get the source state class.
     *
     * @return class-string<StateContract>
     */
    public function fromState(): string;

    /**
     * Get the target state class.
     *
     * @return class-string<StateContract>
     */
    public function toState(): string;
}
