<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\DTOs;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Data Transfer Object for state API resource.
 *
 * Contains all data needed for API response formatting.
 */
final readonly class StateResourceData
{
    /**
     * @param  string  $name  State identifier
     * @param  string  $title  Human-readable title
     * @param  string  $color  UI color
     * @param  string|null  $icon  Optional icon
     * @param  string|null  $description  Optional description
     * @param  bool  $isDefault  Whether this is the default state
     * @param  bool  $isCurrent  Whether model is currently in this state
     * @param  bool  $canTransitionTo  Whether user can transition to this state
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $color,
        public ?string $icon = null,
        public ?string $description = null,
        public bool $isDefault = false,
        public bool $isCurrent = false,
        public bool $canTransitionTo = false,
        public array $metadata = [],
    ) {}

    /**
     * Create from state class.
     *
     * @param  class-string<StateContract>  $stateClass
     * @param  Model|null  $model  For context
     * @param  Authenticatable|null  $user  For permissions
     */
    public static function fromStateClass(
        string $stateClass,
        ?Model $model = null,
        ?Authenticatable $user = null
    ): self {
        $isCurrent = false;
        $canTransitionTo = false;

        if ($model && method_exists($model, 'getState')) {
            $currentState = $model->getState();
            $isCurrent = $currentState && get_class($currentState) === $stateClass;

            // Check if user can transition to this state
            if ($user && method_exists($model, 'userCanTransitionTo')) {
                $canTransitionTo = ! $isCurrent && $model->userCanTransitionTo($user, $stateClass);
            } elseif (method_exists($model, 'canTransitionTo')) {
                $canTransitionTo = ! $isCurrent && $model->canTransitionTo($stateClass);
            }
        }

        return new self(
            name: $stateClass::name(),
            title: $stateClass::title(),
            color: $stateClass::color(),
            icon: method_exists($stateClass, 'icon') ? $stateClass::icon() : null,
            description: method_exists($stateClass, 'description') ? $stateClass::description() : null,
            isDefault: $stateClass::isDefault(),
            isCurrent: $isCurrent,
            canTransitionTo: $canTransitionTo,
            metadata: method_exists($stateClass, 'metadata') ? $stateClass::metadata() : [],
        );
    }

    /**
     * Convert to array for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'color' => $this->color,
            'icon' => $this->icon,
            'description' => $this->description,
            'is_default' => $this->isDefault,
            'is_current' => $this->isCurrent,
            'can_transition_to' => $this->canTransitionTo,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to minimal array (name + title only).
     *
     * @return array{name: string, title: string}
     */
    public function toMinimal(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
        ];
    }

    /**
     * Convert to UI array (for frontend components).
     *
     * @return array<string, mixed>
     */
    public function toUI(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'color' => $this->color,
            'icon' => $this->icon,
            'description' => $this->description,
        ];
    }
}
