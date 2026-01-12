<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\DTOs;

use Illuminate\Database\Eloquent\Model;

/**
 * Result of a transition operation.
 *
 * Indicates success/failure and contains relevant data.
 */
final readonly class TransitionResult
{
    /**
     * @param  bool  $success  Whether transition succeeded
     * @param  Model|null  $model  The transitioned model (on success)
     * @param  string|null  $fromState  Previous state name
     * @param  string|null  $toState  New state name
     * @param  string|null  $error  Error message (on failure)
     * @param  array<string, mixed>  $metadata  Additional result data
     */
    public function __construct(
        public bool $success,
        public ?Model $model = null,
        public ?string $fromState = null,
        public ?string $toState = null,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    /**
     * Create successful result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function success(
        Model $model,
        string $fromState,
        string $toState,
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            model: $model,
            fromState: $fromState,
            toState: $toState,
            metadata: $metadata,
        );
    }

    /**
     * Create failed result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            error: $error,
            metadata: $metadata,
        );
    }

    /**
     * Check if transition succeeded.
     */
    public function succeeded(): bool
    {
        return $this->success;
    }

    /**
     * Check if transition failed.
     */
    public function failed(): bool
    {
        return ! $this->success;
    }
}
