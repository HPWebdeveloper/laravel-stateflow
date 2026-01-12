<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\DTOs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Data Transfer Object for transition operations.
 *
 * Encapsulates all data needed to perform a state transition.
 */
final readonly class TransitionData
{
    /**
     * @param  Model  $model  The model being transitioned
     * @param  string  $field  The state field name
     * @param  string  $fromState  Current state (class name or state name)
     * @param  string  $toState  Target state (class name or state name)
     * @param  Authenticatable|null  $performer  User performing the transition
     * @param  string|null  $reason  Optional reason for transition
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public Model $model,
        public string $field,
        public string $fromState,
        public string $toState,
        public ?Authenticatable $performer = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    /**
     * Create from model and target state.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function make(
        Model $model,
        string $field,
        string $toState,
        ?Authenticatable $performer = null,
        ?string $reason = null,
        array $metadata = [],
    ): self {
        $fromState = $model->getAttribute($field);

        return new self(
            model: $model,
            field: $field,
            fromState: is_object($fromState) ? get_class($fromState) : (string) $fromState,
            toState: $toState,
            performer: $performer ?? auth()->user(),
            reason: $reason,
            metadata: $metadata,
        );
    }
}
