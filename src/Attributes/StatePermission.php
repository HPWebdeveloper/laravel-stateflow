<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Attributes;

use Attribute;

/**
 * Define roles permitted to transition TO this state.
 *
 * @example
 * #[StatePermission(roles: ['admin', 'editor'])]
 * class Published extends PostState {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StatePermission
{
    /**
     * @param  array<string>  $roles  Permitted roles
     */
    public function __construct(
        public array $roles,
    ) {}
}
