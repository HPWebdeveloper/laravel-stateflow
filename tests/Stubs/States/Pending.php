<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

use Hpwebdeveloper\LaravelStateflow\Attributes\AllowTransition;
use Hpwebdeveloper\LaravelStateflow\Attributes\DefaultState;
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\StatePermission;

/**
 * Pending state using attributes.
 */
#[DefaultState]
#[StateMetadata(title: 'Pending', color: 'warning', icon: 'clock')]
#[StatePermission(roles: ['admin', 'customer'])]
#[AllowTransition(to: Processing::class)]
#[AllowTransition(to: Cancelled::class)]
class Pending extends OrderState
{
    protected static ?string $name = 'pending';
}
