<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

/**
 * Rejected state for posts.
 * Uses constants for configuration.
 */
class Rejected extends PostState
{
    public const NAME = 'rejected';

    public const TITLE = 'Rejected';

    public const COLOR = 'danger';

    public const ICON = 'x-circle';

    public const NEXT = [Draft::class];

    public const PERMITTED_ROLES = ['admin', 'editor'];
}
