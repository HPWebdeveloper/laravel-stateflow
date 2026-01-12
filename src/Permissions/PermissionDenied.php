<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Permissions;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Value object representing a permission denial.
 *
 * Contains all information about why a transition was denied.
 *
 * @example
 * $denied = PermissionDenied::make(
 *     user: $user,
 *     model: $post,
 *     field: 'state',
 *     fromState: Draft::class,
 *     toState: Published::class,
 *     reason: 'User lacks required role',
 *     checkerClass: RoleBasedChecker::class
 * );
 */
final readonly class PermissionDenied
{
    public function __construct(
        public Authenticatable $user,
        public Model $model,
        public string $field,
        public string $fromState,
        public string $toState,
        public string $reason,
        public string $checkerClass,
    ) {}

    /**
     * Create from components.
     *
     * @param  class-string<StateContract>|StateContract|string  $fromState
     * @param  class-string<StateContract>|StateContract|string  $toState
     */
    public static function make(
        Authenticatable $user,
        Model $model,
        string $field,
        string|StateContract $fromState,
        string|StateContract $toState,
        string $reason,
        string $checkerClass
    ): self {
        return new self(
            user: $user,
            model: $model,
            field: $field,
            fromState: self::resolveStateName($fromState),
            toState: self::resolveStateName($toState),
            reason: $reason,
            checkerClass: $checkerClass,
        );
    }

    /**
     * Resolve state name from class or instance.
     *
     * @param  class-string<StateContract>|StateContract|string  $state
     */
    protected static function resolveStateName(string|StateContract $state): string
    {
        if ($state instanceof StateContract) {
            return $state->name();
        }

        if (class_exists($state) && method_exists($state, 'name')) {
            return $state::name();
        }

        return $state;
    }

    /**
     * Convert to array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->user->getAuthIdentifier(),
            'model_type' => get_class($this->model),
            'model_id' => $this->model->getKey(),
            'field' => $this->field,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'reason' => $this->reason,
            'checker' => $this->checkerClass,
        ];
    }

    /**
     * Get a human-readable description.
     */
    public function getDescription(): string
    {
        return "Transition from '{$this->fromState}' to '{$this->toState}' denied: {$this->reason}";
    }
}
