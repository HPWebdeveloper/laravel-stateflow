<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\Exceptions\InvalidStateException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Eloquent caster for state attributes.
 *
 * Handles serialization to/from database and instantiation of state objects.
 *
 * @implements CastsAttributes<StateContract, string|StateContract>
 */
class StateCaster implements CastsAttributes
{
    /**
     * @param  class-string<StateContract>  $baseStateClass
     */
    public function __construct(
        protected string $baseStateClass,
    ) {}

    /**
     * Transform the attribute from the database value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?StateContract
    {
        if ($value === null) {
            return null;
        }

        $stateClass = $this->resolveStateClass($value);

        if ($stateClass === null) {
            throw InvalidStateException::unknownState($value, $this->baseStateClass);
        }

        return new $stateClass($model);
    }

    /**
     * Transform the attribute to its database value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // Already a string (state name)
        if (is_string($value)) {
            // Validate it's a known state
            $stateClass = $this->resolveStateClass($value);
            if ($stateClass === null) {
                throw InvalidStateException::unknownState($value, $this->baseStateClass);
            }

            return $value;
        }

        // State instance
        if ($value instanceof StateContract) {
            return $value::name();
        }

        throw InvalidStateException::invalidValue($value);
    }

    /**
     * Resolve state class from stored value.
     *
     * @return class-string<StateContract>|null
     */
    protected function resolveStateClass(string $value): ?string
    {
        // 1. Check registered states
        $registeredStates = StateFlow::getRegisteredStates($this->baseStateClass);

        foreach ($registeredStates as $stateClass) {
            if ($stateClass::name() === $value || $stateClass === $value) {
                return $stateClass;
            }
        }

        // 2. Check if value is directly a class name
        if (class_exists($value) && is_subclass_of($value, $this->baseStateClass)) {
            return $value;
        }

        // 3. Auto-discover states in same directory
        return $this->discoverStateClass($value);
    }

    /**
     * Auto-discover state class from same directory as base class.
     *
     * @return class-string<StateContract>|null
     */
    protected function discoverStateClass(string $value): ?string
    {
        $reflection = new ReflectionClass($this->baseStateClass);
        $fileName = $reflection->getFileName();

        if ($fileName === false) {
            return null;
        }

        $directory = dirname($fileName);
        $namespace = $reflection->getNamespaceName();

        // Scan directory for state classes
        $files = glob($directory.'/*.php');

        if ($files === false) {
            return null;
        }

        foreach ($files as $file) {
            $className = $namespace.'\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($className)) {
                continue;
            }

            if (! is_subclass_of($className, $this->baseStateClass)) {
                continue;
            }

            /** @var class-string<StateContract> $className */
            if ($className::name() === $value) {
                return $className;
            }
        }

        return null;
    }
}
