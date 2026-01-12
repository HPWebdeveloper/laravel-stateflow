<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Events;

use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a state history entry is recorded.
 *
 * Listen to this event to perform side effects after history is recorded,
 * such as sending notifications or triggering external integrations.
 */
class StateHistoryRecorded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly StateHistory $history,
    ) {}

    /**
     * Get the model that was transitioned.
     */
    public function getModel(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->history->model;
    }

    /**
     * Get the from state.
     */
    public function getFromState(): string
    {
        return $this->history->from_state;
    }

    /**
     * Get the to state.
     */
    public function getToState(): string
    {
        return $this->history->to_state;
    }

    /**
     * Get the state field name.
     */
    public function getField(): string
    {
        return $this->history->field;
    }

    /**
     * Check if the transition was automated.
     */
    public function isAutomated(): bool
    {
        return $this->history->isAutomated();
    }

    /**
     * Get summary of the recorded history.
     */
    public function getSummary(): string
    {
        return $this->history->getSummary();
    }
}
