<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Actions\Concerns;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;

/**
 * Provides transition hook functionality for actions.
 *
 * Hooks are optional - override only the ones you need.
 */
trait HasTransitionHooks
{
    /**
     * Called before transition starts.
     * Return false to abort the transition.
     */
    public function beforeTransition(TransitionData $data): bool
    {
        return true;
    }

    /**
     * Called after successful transition.
     */
    public function afterTransition(TransitionData $data, TransitionResult $result): void
    {
        // Override in subclass
    }

    /**
     * Called when transition succeeds.
     */
    public function onSuccess(TransitionData $data, TransitionResult $result): void
    {
        // Override in subclass
    }

    /**
     * Called when transition fails.
     */
    public function onFailure(TransitionData $data, TransitionResult $result): void
    {
        // Override in subclass
    }

    /**
     * Custom validation for this transition.
     * Called before beforeTransition.
     *
     * @return array<string, mixed> Validation rules
     */
    public function validationRules(TransitionData $data): array
    {
        return [];
    }
}
