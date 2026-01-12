<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\DTOs;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Rich context for transition operations.
 *
 * Contains all information needed for hooks, events, and logging.
 * This is the "big picture" object passed through the transition lifecycle.
 */
final class TransitionContext
{
    /**
     * Hooks that have been executed.
     *
     * @var array<string>
     */
    protected array $executedHooks = [];

    /**
     * Custom data attached during transition.
     *
     * @var array<string, mixed>
     */
    protected array $customData = [];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $field,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?Authenticatable $performer,
        public readonly ?string $reason,
        public readonly array $metadata,
        public readonly DateTimeImmutable $initiatedAt,
        public readonly ?string $transitionClass = null,
    ) {}

    /**
     * Create from TransitionData.
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
            initiatedAt: new DateTimeImmutable,
            transitionClass: null,
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
     * Record that a hook was executed.
     */
    public function recordHook(string $hookName): void
    {
        $this->executedHooks[] = $hookName;
    }

    /**
     * Check if a hook was executed.
     */
    public function hookWasExecuted(string $hookName): bool
    {
        return in_array($hookName, $this->executedHooks, true);
    }

    /**
     * Get all executed hooks.
     *
     * @return array<string>
     */
    public function getExecutedHooks(): array
    {
        return $this->executedHooks;
    }

    /**
     * Attach custom data.
     */
    public function attach(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;
    }

    /**
     * Get custom data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->customData[$key] ?? $default;
    }

    /**
     * Check if has custom data.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->customData);
    }

    /**
     * Get all custom data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->customData;
    }

    /**
     * Convert to array for logging/serialization.
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
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'initiated_at' => $this->initiatedAt->format('Y-m-d H:i:s'),
            'transition_class' => $this->transitionClass,
            'executed_hooks' => $this->executedHooks,
        ];
    }
}
