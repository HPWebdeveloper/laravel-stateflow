<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

/**
 * Draft state for posts.
 * Uses constants for configuration.
 */
class Draft extends PostState
{
    public const NAME = 'draft';

    public const TITLE = 'Draft';

    public const COLOR = 'primary';

    public const NEXT = [Review::class];

    public const PERMITTED_ROLES = ['admin', 'author'];

    public const IS_DEFAULT = true;
}
