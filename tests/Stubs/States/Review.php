<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States;

/**
 * Review state for posts.
 * Uses constants for configuration.
 */
class Review extends PostState
{
    public const NAME = 'review';

    public const TITLE = 'Under Review';

    public const COLOR = 'warning';

    public const NEXT = [Published::class, Rejected::class];

    public const PERMITTED_ROLES = ['admin', 'editor'];
}
