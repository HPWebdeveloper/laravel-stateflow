<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\StatePermission;

/**
 * Delivered state using attributes.
 */
#[StateMetadata(title: 'Delivered', color: 'success', icon: 'check')]
#[StatePermission(roles: ['admin'])]
class Delivered extends OrderState
{
    protected static ?string $name = 'delivered';
}
