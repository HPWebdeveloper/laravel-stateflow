<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\DTOs;

use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Data Transfer Object for creating state history entries.
 *
 * Provides a type-safe way to pass history data around and convert
 * to various formats (array, model).
 */
final readonly class StateHistoryData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public Model $model,
        public string $field,
        public string $fromState,
        public string $toState,
        public ?Authenticatable $performer = null,
        public ?string $reason = null,
        public ?array $metadata = null,
        public ?string $transitionClass = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    /**
     * Create from a TransitionContext.
     */
    public static function fromTransitionContext(TransitionContext $context): self
    {
        return new self(
            model: $context->model,
            field: $context->field,
            fromState: $context->fromState,
            toState: $context->toState,
            performer: $context->performer,
            reason: $context->reason,
            metadata: $context->metadata,
            transitionClass: $context->transitionClass,
        );
    }

    /**
     * Create from TransitionData and result.
     */
    public static function fromTransitionData(TransitionData $data, ?TransitionResult $result = null): self
    {
        $fromState = self::resolveStateName($data->fromState);
        $toState = self::resolveStateName($data->toState);

        $metadata = $data->metadata;
        if ($result?->metadata) {
            $metadata = array_merge($metadata, $result->metadata);
        }

        return new self(
            model: $data->model,
            field: $data->field,
            fromState: $fromState,
            toState: $toState,
            performer: $data->performer,
            reason: $data->reason,
            metadata: $metadata,
            transitionClass: null,
        );
    }

    /**
     * Create with request info (IP and user agent).
     */
    public static function fromTransitionContextWithRequest(
        TransitionContext $context,
        ?Request $request = null
    ): self {
        $request ??= request();

        return new self(
            model: $context->model,
            field: $context->field,
            fromState: $context->fromState,
            toState: $context->toState,
            performer: $context->performer,
            reason: $context->reason,
            metadata: $context->metadata,
            transitionClass: $context->transitionClass,
            ipAddress: $request?->ip(),
            userAgent: $request?->userAgent(),
        );
    }

    /**
     * Resolve state name from class or string.
     */
    private static function resolveStateName(string $state): string
    {
        if (class_exists($state) && method_exists($state, 'name')) {
            return $state::name();
        }

        return $state;
    }

    /**
     * Convert to array for database insertion.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'model_type' => get_class($this->model),
            'model_id' => $this->model->getKey(),
            'field' => $this->field,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'performer_id' => $this->performer?->getAuthIdentifier(),
            'performer_type' => $this->performer ? get_class($this->performer) : null,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'transition_class' => $this->transitionClass,
            'ip_address' => $this->ipAddress ?? request()?->ip(),
            'user_agent' => $this->userAgent ?? request()?->userAgent(),
        ];
    }

    /**
     * Create StateHistory model from this DTO.
     */
    public function toModel(): StateHistory
    {
        $modelClass = config('laravel-stateflow.history.model', StateHistory::class);

        return new $modelClass($this->toArray());
    }

    /**
     * Create a new instance with updated performer.
     */
    public function withPerformer(?Authenticatable $performer): self
    {
        return new self(
            model: $this->model,
            field: $this->field,
            fromState: $this->fromState,
            toState: $this->toState,
            performer: $performer,
            reason: $this->reason,
            metadata: $this->metadata,
            transitionClass: $this->transitionClass,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Create a new instance with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            model: $this->model,
            field: $this->field,
            fromState: $this->fromState,
            toState: $this->toState,
            performer: $this->performer,
            reason: $this->reason,
            metadata: array_merge($this->metadata ?? [], $metadata),
            transitionClass: $this->transitionClass,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
        );
    }

    /**
     * Create a new instance with reason.
     */
    public function withReason(?string $reason): self
    {
        return new self(
            model: $this->model,
            field: $this->field,
            fromState: $this->fromState,
            toState: $this->toState,
            performer: $this->performer,
            reason: $reason,
            metadata: $this->metadata,
            transitionClass: $this->transitionClass,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
        );
    }
}
