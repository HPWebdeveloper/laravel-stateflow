<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\DTOs;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;

/**
 * Data Transfer Object for state information.
 *
 * Encapsulates all state data for clean boundaries between layers.
 * Following Invitex pattern of using readonly DTOs.
 */
final readonly class StateData
{
    /**
     * @param  string  $name  State identifier (stored in DB)
     * @param  string  $title  Human-readable title
     * @param  string  $color  UI color
     * @param  string|null  $icon  Optional icon identifier
     * @param  string|null  $description  Optional description
     * @param  array<class-string<StateContract>>  $allowedTransitions  Next state classes
     * @param  array<string>  $permittedRoles  Roles that can transition to this state
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $color,
        public ?string $icon = null,
        public ?string $description = null,
        public array $allowedTransitions = [],
        public array $permittedRoles = [],
        public array $metadata = [],
    ) {}

    /**
     * Create from state class.
     *
     * @param  class-string<StateContract>  $stateClass
     */
    public static function fromStateClass(string $stateClass): self
    {
        return new self(
            name: $stateClass::name(),
            title: $stateClass::title(),
            color: $stateClass::color(),
            icon: method_exists($stateClass, 'icon') ? $stateClass::icon() : null,
            description: method_exists($stateClass, 'description') ? $stateClass::description() : null,
            allowedTransitions: $stateClass::allowedTransitions(),
            permittedRoles: $stateClass::permittedRoles(),
            metadata: method_exists($stateClass, 'metadata') ? $stateClass::metadata() : [],
        );
    }

    /**
     * Convert to array for API/resources.
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
            'allowed_transitions' => $this->allowedTransitions,
            'permitted_roles' => $this->permittedRoles,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to resource array (UI-ready).
     *
     * @return array<string, mixed>
     */
    public function toResource(): array
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
