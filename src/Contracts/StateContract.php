<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for state classes.
 *
 * Each concrete state must implement this interface to define
 * its behavior, metadata, and transition rules.
 */
interface StateContract
{
    /**
     * Get the state's identifier (stored in database).
     */
    public static function name(): string;

    /**
     * Get the human-readable title for UI display.
     */
    public static function title(): string;

    /**
     * Get the UI color for this state.
     */
    public static function color(): string;

    /**
     * Get allowed next states this state can transition to.
     *
     * @return array<class-string<StateContract>>
     */
    public static function allowedTransitions(): array;

    /**
     * Get roles permitted to transition INTO this state.
     *
     * @return array<string>
     */
    public static function permittedRoles(): array;

    /**
     * Convert state to resource array for API/UI.
     *
     * @return array<string, mixed>
     */
    public function toResource(): array;

    /**
     * Get the model instance this state is attached to.
     */
    public function getModel(): Model;
}
