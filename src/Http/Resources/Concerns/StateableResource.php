<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Http\Resources\Concerns;

use Hpwebdeveloper\LaravelStateflow\DTOs\StateResourceData;
use Hpwebdeveloper\LaravelStateflow\Http\Resources\StateCollectionResource;
use Hpwebdeveloper\LaravelStateflow\Http\Resources\StateResource;
use Illuminate\Http\Request;

/**
 * Trait for adding state data to model resources.
 *
 * Include this trait in your model's API resource to automatically
 * include state information and available transitions.
 *
 * @example
 * class PostResource extends JsonResource
 * {
 *     use StateableResource;
 *
 *     public function toArray(Request $request): array
 *     {
 *         return [
 *             'id' => $this->id,
 *             'title' => $this->title,
 *             // Include state data
 *             ...$this->stateData($request),
 *             // Or include as nested
 *             'state_info' => $this->stateResource($request),
 *         ];
 *     }
 * }
 */
trait StateableResource
{
    /**
     * Get state data for inclusion in resource.
     *
     * Returns flattened state data: state, state_title, state_color, next_states
     *
     * @return array<string, mixed>
     */
    protected function stateData(Request $request, ?string $field = null): array
    {
        $model = $this->resource;

        if (! method_exists($model, 'getState')) {
            return [];
        }

        $state = $model->getState($field);
        if (! $state) {
            return ['state' => null];
        }

        $user = $request->user();
        $stateData = StateResourceData::fromStateClass(get_class($state), $model, $user);

        // Get next states
        $nextStates = [];
        if (method_exists($model, 'getNextStatesForUser') && $user) {
            $nextStates = $model->getNextStatesForUser($user, $field);
        } elseif (method_exists($model, 'getNextStates')) {
            $nextStates = $model->getNextStates($field);
        }

        return [
            'state' => $stateData->name,
            'state_title' => $stateData->title,
            'state_color' => $stateData->color,
            'state_icon' => $stateData->icon,
            'next_states' => array_map(function ($nextStateClass) use ($model, $user) {
                return StateResourceData::fromStateClass($nextStateClass, $model, $user)->toUI();
            }, $nextStates),
        ];
    }

    /**
     * Get full state resource as nested object.
     *
     * @return array<string, mixed>
     */
    protected function stateResource(Request $request, ?string $field = null): array
    {
        $model = $this->resource;

        if (! method_exists($model, 'getState')) {
            return ['current' => null, 'available' => []];
        }

        $state = $model->getState($field);
        $user = $request->user();

        return [
            'current' => $state
                ? (new StateResource($state))->withModel($model)->withUser($user)->toArray($request)
                : null,
            'available' => StateCollectionResource::nextStates($model, $user, $field)
                ->ui()
                ->toArray($request),
        ];
    }

    /**
     * Get minimal state data (name only).
     *
     * @return array{state: string|null}
     */
    protected function stateMinimal(Request $request, ?string $field = null): array
    {
        $model = $this->resource;

        if (! method_exists($model, 'getStateName')) {
            return ['state' => null];
        }

        return ['state' => $model->getStateName($field)];
    }
}
