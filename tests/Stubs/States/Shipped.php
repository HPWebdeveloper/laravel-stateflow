<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

use Hpwebdeveloper\LaravelStateflow\Attributes\AllowTransition;
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\StatePermission;

/**
 * Shipped state using attributes.
 */
#[StateMetadata(title: 'Shipped', color: 'primary', icon: 'truck')]
#[StatePermission(roles: ['admin'])]
#[AllowTransition(to: Delivered::class)]
class Shipped extends OrderState
{
    protected static ?string $name = 'shipped';
}
