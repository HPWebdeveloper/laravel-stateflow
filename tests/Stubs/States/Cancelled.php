<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\StatePermission;

/**
 * Cancelled state using attributes.
 */
#[StateMetadata(title: 'Cancelled', color: 'danger', icon: 'x')]
#[StatePermission(roles: ['admin', 'customer'])]
class Cancelled extends OrderState
{
    protected static ?string $name = 'cancelled';
}
