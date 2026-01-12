<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;

/**
 * Contract for models that have states.
 *
 * Ensures consistent API across all stateful models.
 */
interface HasStatesContract
{
    /**
     * Register states for this model.
     */
    public static function registerStates(): void;

    /**
     * Get state configuration for a field.
     */
    public static function getStateConfig(string $field): ?StateConfig;

    /**
     * Get the current state for a field.
     */
    public function getState(?string $field = null): ?StateContract;

    /**
     * Check if model can transition to a state.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    public function canTransitionTo(string|StateContract $state, ?string $field = null): bool;

    /**
     * Transition to a new state.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     * @param  array<string, mixed>  $metadata
     */
    public function transitionTo(
        string|StateContract $state,
        ?string $field = null,
        ?string $reason = null,
        array $metadata = []
    ): TransitionResult;
}
