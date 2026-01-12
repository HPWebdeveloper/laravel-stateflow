<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Http\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * JSON API Resource for a collection of states.
 *
 * Useful for returning all possible states or next states.
 *
 * @example
 * // All states for a model type
 * return StateCollectionResource::forModel(Post::class);
 *
 * // Next states for a specific model
 * return StateCollectionResource::nextStates($post);
 *
 * // Next states with user permission filtering
 * return StateCollectionResource::nextStates($post, auth()->user());
 */
class StateCollectionResource extends ResourceCollection
{
    /**
     * Model for context.
     */
    protected ?Model $contextModel = null;

    /**
     * User for permission checking.
     */
    protected ?Authenticatable $contextUser = null;

    /**
     * Output format.
     */
    protected string $format = 'full';

    /**
     * Create a resource for all states of a model.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function forModel(string $modelClass): self
    {
        if (! method_exists($modelClass, 'getStateConfig')) {
            return new self([]);
        }

        // Get the first state field
        if (method_exists($modelClass, 'getAllStateConfigs')) {
            $configs = $modelClass::getAllStateConfigs();
            $config = reset($configs);

            if (! $config) {
                return new self([]);
            }

            return new self($config->getStates());
        }

        return new self([]);
    }

    /**
     * Create a resource for next states of a model instance.
     */
    public static function nextStates(
        Model $model,
        ?Authenticatable $user = null,
        ?string $field = null
    ): self {
        $states = [];

        if (method_exists($model, 'getNextStates')) {
            if ($user && method_exists($model, 'getNextStatesForUser')) {
                $states = $model->getNextStatesForUser($user, $field);
            } else {
                $states = $model->getNextStates($field);
            }
        }

        return (new self($states))
            ->withModel($model)
            ->withUser($user);
    }

    /**
     * Set model context.
     */
    public function withModel(?Model $model): self
    {
        $this->contextModel = $model;

        return $this;
    }

    /**
     * Set user context.
     */
    public function withUser(?Authenticatable $user): self
    {
        $this->contextUser = $user;

        return $this;
    }

    /**
     * Use minimal format.
     */
    public function minimal(): self
    {
        $this->format = 'minimal';

        return $this;
    }

    /**
     * Use UI format.
     */
    public function ui(): self
    {
        $this->format = 'ui';

        return $this;
    }

    /**
     * Use full format.
     */
    public function full(): self
    {
        $this->format = 'full';

        return $this;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $user = $this->contextUser ?? $request->user();

        return $this->collection->map(function ($stateClass) use ($request, $user) {
            $resource = (new StateResource($stateClass))
                ->withModel($this->contextModel)
                ->withUser($user);

            return match ($this->format) {
                'minimal' => $resource->minimal()->toArray($request),
                'ui' => $resource->ui()->toArray($request),
                default => $resource->toArray($request),
            };
        })->all();
    }
}
