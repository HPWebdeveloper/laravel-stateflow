<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Events;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateFlowEvent;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired BEFORE a state transition occurs.
 *
 * Listeners can cancel the transition by calling $event->cancel().
 * This is useful for implementing business rule validations or
 * approval workflows that need to prevent certain transitions.
 *
 * @example
 * Event::listen(StateTransitioning::class, function ($event) {
 *     if ($event->toState === 'published' && !$event->model->isComplete()) {
 *         $event->cancel('Model is not complete');
 *     }
 * });
 */
class StateTransitioning implements StateFlowEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Flag to indicate if transition should be cancelled.
     */
    public bool $shouldCancel = false;

    /**
     * Reason for cancellation (if cancelled).
     */
    public ?string $cancellationReason = null;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $field,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?Authenticatable $performer = null,
        public readonly ?string $reason = null,
        public readonly ?array $metadata = null,
    ) {}

    /**
     * Create from TransitionData DTO.
     */
    public static function fromTransitionData(TransitionData $data): self
    {
        return new self(
            model: $data->model,
            field: $data->field,
            fromState: self::resolveStateName($data->fromState),
            toState: self::resolveStateName($data->toState),
            performer: $data->performer,
            reason: $data->reason,
            metadata: $data->metadata,
        );
    }

    /**
     * Resolve a state class name to its short name.
     */
    protected static function resolveStateName(string $state): string
    {
        // If it's a class name, get the short name via the static name() method
        if (class_exists($state) && method_exists($state, 'name')) {
            return $state::name();
        }

        return $state;
    }

    /**
     * Cancel the transition.
     */
    public function cancel(?string $reason = null): void
    {
        $this->shouldCancel = true;
        $this->cancellationReason = $reason;
    }

    /**
     * Check if transition was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->shouldCancel;
    }

    /**
     * Get the model involved in this event.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the state field name.
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Get the model class name.
     */
    public function getModelClass(): string
    {
        return get_class($this->model);
    }

    /**
     * Get the model key.
     */
    public function getModelKey(): mixed
    {
        return $this->model->getKey();
    }

    /**
     * Get a summary of the transitioning event.
     */
    public function getSummary(): string
    {
        $performerName = $this->performer?->name ?? 'System';

        return sprintf(
            '%s is transitioning %s#%s from %s to %s',
            $performerName,
            class_basename($this->model),
            $this->model->getKey(),
            $this->fromState,
            $this->toState
        );
    }
}
