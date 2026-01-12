<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Http\Resources;

use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\StateResourceData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON API Resource for a single state.
 *
 * Provides formatted state data for API responses.
 *
 * @example
 * // In a controller
 * return new StateResource($post->state);
 *
 * // With context for permissions
 * return StateResource::make($stateClass)
 *     ->withModel($post)
 *     ->withUser(auth()->user());
 *
 * @property-read StateContract|string $resource
 */
class StateResource extends JsonResource
{
    /**
     * Model for context (permissions, current state).
     */
    protected ?Model $contextModel = null;

    /**
     * User for permission checking.
     */
    protected ?Authenticatable $contextUser = null;

    /**
     * Output format: 'full', 'minimal', 'ui'
     */
    protected string $format = 'full';

    /**
     * Create a new resource instance.
     *
     * @param  StateContract|class-string<StateContract>|string  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * Set model context for permissions and current state.
     */
    public function withModel(?Model $model): self
    {
        $this->contextModel = $model;

        return $this;
    }

    /**
     * Set user context for permission checking.
     */
    public function withUser(?Authenticatable $user): self
    {
        $this->contextUser = $user;

        return $this;
    }

    /**
     * Use minimal format (name + title only).
     */
    public function minimal(): self
    {
        $this->format = 'minimal';

        return $this;
    }

    /**
     * Use UI format (with color, icon, description).
     */
    public function ui(): self
    {
        $this->format = 'ui';

        return $this;
    }

    /**
     * Use full format (all data).
     */
    public function full(): self
    {
        $this->format = 'full';

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stateClass = $this->resolveStateClass();
        $user = $this->contextUser ?? $request->user();

        $data = StateResourceData::fromStateClass(
            $stateClass,
            $this->contextModel,
            $user
        );

        return match ($this->format) {
            'minimal' => $data->toMinimal(),
            'ui' => $data->toUI(),
            default => $data->toArray(),
        };
    }

    /**
     * Resolve the state class from resource.
     *
     * @return class-string<StateContract>
     */
    protected function resolveStateClass(): string
    {
        if ($this->resource instanceof StateContract) {
            return get_class($this->resource);
        }

        return $this->resource;
    }
}
