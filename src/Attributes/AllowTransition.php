<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Attributes;

use Attribute;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;

/**
 * Define an allowed transition from this state.
 *
 * Can be used multiple times on a state class to define
 * multiple allowed transitions.
 *
 * @example
 * #[AllowTransition(to: Published::class)]
 * #[AllowTransition(to: Rejected::class, transition: RejectPost::class)]
 * class Review extends PostState {}
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AllowTransition
{
    /**
     * @param  class-string<StateContract>  $to  Target state class
     * @param  class-string|null  $transition  Optional custom transition class
     */
    public function __construct(
        public string $to,
        public ?string $transition = null,
    ) {}
}
