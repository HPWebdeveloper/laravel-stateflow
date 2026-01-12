<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Events;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateFlowEvent;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired AFTER a state transition has completed successfully.
 *
 * This event is fired after the transition has been persisted to the database.
 * It's useful for logging, notifications, and triggering side effects that
 * should only occur after successful transitions.
 *
 * @example
 * Event::listen(StateTransitioned::class, function ($event) {
 *     logger()->info('State changed', [
 *         'model' => $event->getModelClass(),
 *         'from' => $event->fromState,
 *         'to' => $event->toState,
 *     ]);
 * });
 */
class StateTransitioned implements StateFlowEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $field,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?Authenticatable $performer = null,
        public readonly ?string $reason = null,
        public readonly ?array $metadata = null,
        public readonly ?array $context = null,
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
     * Create from TransitionContext.
     *
     * @param  array<string, mixed>|null  $additionalContext
     */
    public static function fromContext(TransitionContext $context, ?array $additionalContext = null): self
    {
        return new self(
            model: $context->model,
            field: $context->field,
            fromState: self::resolveStateName($context->fromState),
            toState: self::resolveStateName($context->toState),
            performer: $context->performer,
            reason: $context->reason,
            metadata: $context->metadata,
            context: $additionalContext,
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
     * Create from TransitionResult.
     */
    public static function fromResult(TransitionResult $result): self
    {
        $context = $result->context;

        return new self(
            model: $context->model,
            field: $context->field,
            fromState: is_object($context->fromState) ? $context->fromState::name() : $context->fromState,
            toState: is_object($context->toState) ? $context->toState::name() : $context->toState,
            performer: $context->performer,
            reason: $context->reason,
            metadata: $context->metadata,
            context: [
                'history_id' => $result->historyId,
            ],
        );
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
     * Get the history ID if available.
     */
    public function getHistoryId(): ?int
    {
        return $this->context['history_id'] ?? null;
    }

    /**
     * Get a summary of the transition.
     */
    public function getSummary(): string
    {
        $performerName = $this->performer?->name ?? 'System';

        return sprintf(
            '%s transitioned %s#%s from %s to %s',
            $performerName,
            class_basename($this->model),
            $this->model->getKey(),
            $this->fromState,
            $this->toState
        );
    }

    /**
     * Get details as array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'model_type' => $this->getModelClass(),
            'model_id' => $this->getModelKey(),
            'field' => $this->field,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'performer_id' => $this->performer?->getAuthIdentifier(),
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'context' => $this->context,
        ];
    }
}
