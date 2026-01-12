<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Http\Resources;

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON API Resource for a transition result.
 *
 * Use this when returning the result of a state transition.
 *
 * @example
 * $result = $post->transitionTo(Published::class);
 * return new TransitionResource($result);
 *
 * @property-read TransitionResult $resource
 */
class TransitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->resource->succeeded(),
            'from_state' => $this->resource->fromState,
            'to_state' => $this->resource->toState,
            'error' => $this->when($this->resource->failed(), $this->resource->error),
            'model' => $this->when(
                $this->resource->succeeded() && $this->resource->model,
                function () {
                    return [
                        'id' => $this->resource->model->getKey(),
                        'type' => class_basename($this->resource->model),
                    ];
                }
            ),
            'metadata' => $this->when(
                ! empty($this->resource->metadata),
                $this->resource->metadata
            ),
        ];
    }
}
