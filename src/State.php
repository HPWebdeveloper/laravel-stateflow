<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Attributes\AllowTransition;
use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\StatePermission;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\StateData;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use ReflectionClass;

/**
 * Abstract base class for all states.
 *
 * States can be defined using either:
 * 1. Class constants (NAME, TITLE, COLOR, NEXT, PERMITTED_ROLES, etc.)
 * 2. PHP 8+ attributes (#[AllowTransition], #[StatePermission], etc.)
 * 3. Override methods (allowedTransitions(), permittedRoles(), etc.)
 *
 * @example Using constants:
 * class Draft extends PostState
 * {
 *     const NAME = 'draft';
 *     const TITLE = 'Draft';
 *     const COLOR = 'primary';
 *     const NEXT = [Review::class, Rejected::class];
 *     const PERMITTED_ROLES = ['admin', 'author'];
 * }
 * @example Using attributes:
 * #[StateMetadata(title: 'Draft', color: 'primary')]
 * #[StatePermission(roles: ['admin', 'author'])]
 * #[AllowTransition(to: Review::class)]
 * #[AllowTransition(to: Rejected::class)]
 * class Draft extends PostState {}
 */
abstract class State implements Castable, JsonSerializable, StateContract
{
    /**
     * The model instance this state is attached to.
     */
    protected Model $model;

    /**
     * Static name for this state (stored in database).
     * Override this in child classes or define NAME constant.
     */
    protected static ?string $name = null;

    /**
     * Create a new state instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get the state's identifier (stored in database).
     */
    public static function name(): string
    {
        // 1. Check for static property
        if (static::$name !== null) {
            return static::$name;
        }

        // 2. Check for NAME constant
        if (defined(static::class.'::NAME')) {
            /** @phpstan-ignore-next-line */
            return static::NAME;
        }

        // 3. Derive from class name (Draft -> draft)
        $className = (new ReflectionClass(static::class))->getShortName();

        return strtolower($className);
    }

    /**
     * Get the human-readable title for UI display.
     */
    public static function title(): string
    {
        // 1. Check for TITLE constant
        if (defined(static::class.'::TITLE')) {
            /** @phpstan-ignore-next-line */
            return static::TITLE;
        }

        // 2. Check for StateMetadata attribute
        $attribute = static::getStateMetadataAttribute();
        if ($attribute) {
            return $attribute->title;
        }

        // 3. Derive from class name (Draft -> Draft)
        return (new ReflectionClass(static::class))->getShortName();
    }

    /**
     * Get the UI color for this state.
     */
    public static function color(): string
    {
        // 1. Check for COLOR constant
        if (defined(static::class.'::COLOR')) {
            /** @phpstan-ignore-next-line */
            return static::COLOR;
        }

        // 2. Check for StateMetadata attribute
        $attribute = static::getStateMetadataAttribute();
        if ($attribute) {
            return $attribute->color;
        }

        // 3. Default from config
        return config('laravel-stateflow.resource_defaults.color', 'gray');
    }

    /**
     * Get optional icon identifier.
     */
    public static function icon(): ?string
    {
        // 1. Check for ICON constant
        if (defined(static::class.'::ICON')) {
            /** @phpstan-ignore-next-line */
            return static::ICON;
        }

        // 2. Check for StateMetadata attribute
        $attribute = static::getStateMetadataAttribute();
        if ($attribute) {
            return $attribute->icon;
        }

        return config('laravel-stateflow.resource_defaults.icon');
    }

    /**
     * Get optional description.
     */
    public static function description(): ?string
    {
        // 1. Check for DESCRIPTION constant
        if (defined(static::class.'::DESCRIPTION')) {
            /** @phpstan-ignore-next-line */
            return static::DESCRIPTION;
        }

        // 2. Check for StateMetadata attribute
        $attribute = static::getStateMetadataAttribute();
        if ($attribute) {
            return $attribute->description;
        }

        return config('laravel-stateflow.resource_defaults.description');
    }

    /**
     * Get allowed next states this state can transition to.
     *
     * @return array<class-string<StateContract>>
     */
    public static function allowedTransitions(): array
    {
        // 1. Check for NEXT constant
        if (defined(static::class.'::NEXT')) {
            /** @phpstan-ignore-next-line */
            return static::NEXT;
        }

        // 2. Check for AllowTransition attributes
        $attributes = static::getAllowTransitionAttributes();
        if (! empty($attributes)) {
            return array_map(fn (AllowTransition $attr) => $attr->to, $attributes);
        }

        return [];
    }

    /**
     * Get roles permitted to transition INTO this state.
     *
     * @return array<string>
     */
    public static function permittedRoles(): array
    {
        // 1. Check for PERMITTED_ROLES constant
        if (defined(static::class.'::PERMITTED_ROLES')) {
            /** @phpstan-ignore-next-line */
            $roles = static::PERMITTED_ROLES;

            // Handle enum arrays (like UserRole enum)
            return array_map(function ($role) {
                if ($role instanceof \BackedEnum) {
                    return (string) $role->value;
                }

                return $role;
            }, $roles);
        }

        // 2. Check for StatePermission attribute
        $attribute = static::getStatePermissionAttribute();
        if ($attribute) {
            return $attribute->roles;
        }

        // 3. Empty = anyone can transition to this state
        return [];
    }

    /**
     * Check if this is the default state.
     */
    public static function isDefault(): bool
    {
        // 1. Check for IS_DEFAULT constant
        if (defined(static::class.'::IS_DEFAULT')) {
            /** @phpstan-ignore-next-line */
            return static::IS_DEFAULT;
        }

        // 2. Check for DefaultState attribute
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(DefaultState::class);

        return ! empty($attributes);
    }

    /**
     * Get additional metadata.
     *
     * @return array<string, mixed>
     */
    public static function metadata(): array
    {
        if (defined(static::class.'::METADATA')) {
            /** @phpstan-ignore-next-line */
            return static::METADATA;
        }

        return [];
    }

    /**
     * Check if this state can transition to another state.
     *
     * @param  class-string<StateContract>|StateContract  $state
     */
    public static function canTransitionTo(string|StateContract $state): bool
    {
        $targetClass = is_string($state) ? $state : get_class($state);

        return in_array($targetClass, static::allowedTransitions(), true);
    }

    /**
     * Get the custom transition class for a transition (if defined).
     *
     * @param  class-string<StateContract>  $toState
     * @return class-string|null
     */
    public static function getTransitionClass(string $toState): ?string
    {
        // 1. Check StateFlow registry
        $registered = StateFlow::getTransitionClass(static::class, $toState);
        if ($registered) {
            return $registered;
        }

        // 2. Check AllowTransition attributes
        $attributes = static::getAllowTransitionAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->to === $toState && $attribute->transition) {
                return $attribute->transition;
            }
        }

        return null;
    }

    /**
     * Convert state to resource array for API/UI.
     *
     * @return array<string, mixed>
     */
    public function toResource(): array
    {
        return StateData::fromStateClass(static::class)->toResource();
    }

    /**
     * Get the model instance this state is attached to.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the morph class for database storage.
     */
    public static function getMorphClass(): string
    {
        return static::name();
    }

    /**
     * Create a StateCaster instance for Eloquent.
     *
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): StateCaster
    {
        return new StateCaster(static::class);
    }

    /**
     * JSON serialization.
     */
    public function jsonSerialize(): string
    {
        return static::name();
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return static::name();
    }

    /**
     * Check equality with another state.
     */
    public function equals(State|string $other): bool
    {
        if ($other instanceof State) {
            return get_class($this) === get_class($other);
        }

        return static::name() === $other || static::class === $other;
    }

    // -------------------------------------------------------------------------
    // Protected helper methods for attribute parsing
    // -------------------------------------------------------------------------

    /**
     * Get StateMetadata attribute if present.
     */
    protected static function getStateMetadataAttribute(): ?StateMetadata
    {
        if (! StateFlow::hasFeature('attributes')) {
            return null;
        }

        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(StateMetadata::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get StatePermission attribute if present.
     */
    protected static function getStatePermissionAttribute(): ?StatePermission
    {
        if (! StateFlow::hasFeature('attributes')) {
            return null;
        }

        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(StatePermission::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get all AllowTransition attributes.
     *
     * @return array<AllowTransition>
     */
    protected static function getAllowTransitionAttributes(): array
    {
        if (! StateFlow::hasFeature('attributes')) {
            return [];
        }

        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(AllowTransition::class);

        return array_map(fn ($attr) => $attr->newInstance(), $attributes);
    }
}
