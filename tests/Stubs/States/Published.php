<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

/**
 * Published state for posts.
 * Uses constants for configuration.
 */
class Published extends PostState
{
    public const NAME = 'published';

    public const TITLE = 'Published';

    public const COLOR = 'success';

    public const ICON = 'check-circle';

    public const NEXT = [];

    public const PERMITTED_ROLES = ['admin'];
}
