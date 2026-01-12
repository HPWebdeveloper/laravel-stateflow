<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Events;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateFlowEvent;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Event fired when a state transition fails.
 *
 * This event is fired whenever a transition cannot be completed due to
 * validation errors, exceptions, or business rule violations.
 *
 * @example
 * Event::listen(TransitionFailed::class, function ($event) {
 *     logger()->error('Transition failed', [
 *         'model' => $event->getModelClass(),
 *         'error' => $event->error,
 *         'exception' => $event->exception?->getMessage(),
 *     ]);
 * });
 */
class TransitionFailed implements StateFlowEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

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
        public readonly string $error,
        public readonly ?string $errorCode = null,
        public readonly ?Throwable $exception = null,
        public readonly ?Authenticatable $performer = null,
        public readonly ?string $reason = null,
        public readonly ?array $metadata = null,
    ) {}

    /**
     * Create from TransitionData DTO.
     */
    public static function fromTransitionData(
        TransitionData $data,
        string $error,
        ?string $errorCode = null,
        ?Throwable $exception = null
    ): self {
        return new self(
            model: $data->model,
            field: $data->field,
            fromState: self::resolveStateName($data->fromState),
            toState: self::resolveStateName($data->toState),
            error: $error,
            errorCode: $errorCode,
            exception: $exception,
            performer: $data->performer,
            reason: $data->reason,
            metadata: $data->metadata,
        );
    }

    /**
     * Create from TransitionContext.
     */
    public static function fromContext(
        TransitionContext $context,
        string $error,
        ?string $errorCode = null,
        ?Throwable $exception = null
    ): self {
        return new self(
            model: $context->model,
            field: $context->field,
            fromState: self::resolveStateName($context->fromState),
            toState: self::resolveStateName($context->toState),
            error: $error,
            errorCode: $errorCode,
            exception: $exception,
            performer: $context->performer,
            reason: $context->reason,
            metadata: $context->metadata,
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
     * Check if the failure was caused by an exception.
     */
    public function hasException(): bool
    {
        return $this->exception !== null;
    }

    /**
     * Get the exception message if available.
     */
    public function getExceptionMessage(): ?string
    {
        return $this->exception?->getMessage();
    }

    /**
     * Get a summary of the error.
     */
    public function getErrorSummary(): string
    {
        $summary = sprintf(
            'Failed to transition %s#%s from %s to %s: %s',
            class_basename($this->model),
            $this->model->getKey(),
            $this->fromState,
            $this->toState,
            $this->error
        );

        if ($this->errorCode) {
            $summary .= " [{$this->errorCode}]";
        }

        return $summary;
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
            'error' => $this->error,
            'error_code' => $this->errorCode,
            'exception_class' => $this->exception ? get_class($this->exception) : null,
            'exception_message' => $this->getExceptionMessage(),
            'performer_id' => $this->performer?->getAuthIdentifier(),
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }
}
