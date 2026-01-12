<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Attributes;

use Attribute;

/**
 * Mark a state class as the default state.
 *
 * When a model is created without a state, this state will be used.
 *
 * @example
 * #[DefaultState]
 * class Draft extends PostState {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DefaultState
{
    public function __construct() {}
}
