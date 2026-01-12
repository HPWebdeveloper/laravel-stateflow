<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Contracts;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;

/**
 * Contract for transition hooks.
 *
 * Allows custom logic before/after transitions.
 */
interface TransitionHooksContract
{
    /**
     * Called before transition starts.
     * Return false to abort the transition.
     */
    public function beforeTransition(TransitionData $data): bool;

    /**
     * Called after successful transition.
     */
    public function afterTransition(TransitionData $data, TransitionResult $result): void;

    /**
     * Called when transition succeeds.
     */
    public function onSuccess(TransitionData $data, TransitionResult $result): void;

    /**
     * Called when transition fails.
     */
    public function onFailure(TransitionData $data, TransitionResult $result): void;
}
