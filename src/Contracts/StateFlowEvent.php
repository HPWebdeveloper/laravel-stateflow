<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Marker interface for StateFlow events.
 *
 * All StateFlow events implement this interface for
 * easy identification and filtering.
 */
interface StateFlowEvent
{
    /**
     * Get the model involved in this event.
     */
    public function getModel(): Model;

    /**
     * Get the state field name.
     */
    public function getField(): string;
}
