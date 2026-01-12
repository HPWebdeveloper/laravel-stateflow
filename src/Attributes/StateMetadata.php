<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Attributes;

use Attribute;

/**
 * Define UI metadata for a state.
 *
 * @example
 * #[StateMetadata(title: 'Published', color: 'success', icon: 'check-circle')]
 * class Published extends PostState {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StateMetadata
{
    /**
     * @param  string  $title  Human-readable title
     * @param  string  $color  UI color (success, warning, danger, etc.)
     * @param  string|null  $icon  Optional icon identifier
     * @param  string|null  $description  Optional description
     */
    public function __construct(
        public string $title,
        public string $color = 'gray',
        public ?string $icon = null,
        public ?string $description = null,
    ) {}
}
