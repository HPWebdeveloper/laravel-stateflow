<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

use Hpwebdeveloper\LaravelStateflow\Attributes\AllowTransition;
use Hpwebdeveloper\LaravelStateflow\Attributes\StateMetadata;
use Hpwebdeveloper\LaravelStateflow\Attributes\StatePermission;

/**
 * Processing state using attributes.
 */
#[StateMetadata(title: 'Processing', color: 'info', icon: 'cog')]
#[StatePermission(roles: ['admin'])]
#[AllowTransition(to: Shipped::class)]
#[AllowTransition(to: Cancelled::class)]
class Processing extends OrderState
{
    protected static ?string $name = 'processing';
}
