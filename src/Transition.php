<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow;

use Hpwebdeveloper\LaravelStateflow\Actions\RecordStateTransition;
use Hpwebdeveloper\LaravelStateflow\Contracts\StateContract;
use Hpwebdeveloper\LaravelStateflow\Contracts\TransitionContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionException;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Default transition implementation.
 *
 * Handles the basic transition logic without custom behavior.
 * Extend this class to add custom logic for specific transitions.
 */
class Transition implements TransitionContract
{
    /**
     * The model being transitioned.
     *
     * @var Model&HasStatesContract
     */
    protected Model $model;

    /**
     * The field being transitioned.
     */
    protected string $field;

    /**
     * The source state class.
     *
     * @var class-string<StateContract>|null
     */
    protected ?string $fromStateClass = null;

    /**
     * The target state class.
     *
     * @var class-string<StateContract>
     */
    protected string $targetStateClass;

    /**
     * Reason for the transition.
     */
    protected ?string $reason = null;

    /**
     * Additional metadata.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Create a new transition instance.
     *
     * @param  Model&HasStatesContract  $model
     * @param  class-string<StateContract>  $targetStateClass
     */
    public function __construct(Model $model, string $field, string $targetStateClass)
    {
        $this->model = $model;
        $this->field = $field;
        $this->targetStateClass = $targetStateClass;

        // Store the current state as fromState
        /** @var StateContract|null $currentState */
        $currentState = $model->{$field};
        $this->fromStateClass = $currentState !== null ? $currentState::class : null;
    }

    /**
     * Check if transition can be executed.
     */
    public function canTransition(): bool
    {
        return $this->model->canTransitionTo($this->targetStateClass, $this->field);
    }

    /**
     * Check if transition can be executed (alias for consistency).
     */
    public function canExecute(): bool
    {
        return $this->canTransition();
    }

    /**
     * Get the source state class.
     *
     * @return class-string<StateContract>
     */
    public function fromState(): string
    {
        // @phpstan-ignore-next-line Returns empty string if no from state
        return $this->fromStateClass ?? '';
    }

    /**
     * Get the target state class.
     *
     * @return class-string<StateContract>
     */
    public function toState(): string
    {
        return $this->targetStateClass;
    }

    /**
     * Execute the transition (implements TransitionContract).
     */
    public function handle(): TransitionResult
    {
        return $this->execute();
    }

    /**
     * Execute the transition.
     */
    public function execute(): TransitionResult
    {
        /** @var StateContract|null $fromState */
        $fromState = $this->model->{$this->field};
        $fromStateName = $fromState?->name() ?? 'null';
        $toStateName = $this->targetStateClass::name();

        // Dispatch StateTransitioning event if events are enabled
        if ($this->eventsEnabled()) {
            $transitioningEvent = new StateTransitioning(
                model: $this->model,
                field: $this->field,
                fromState: $fromStateName,
                toState: $toStateName,
                performer: auth()->user(),
                reason: $this->reason,
                metadata: $this->metadata,
            );

            event($transitioningEvent);

            // Check if transition was cancelled
            if ($transitioningEvent->isCancelled()) {
                $reason = $transitioningEvent->cancellationReason ?? 'Cancelled by listener';

                throw TransitionException::cancelledByEvent($reason);
            }
        }

        try {
            // Call before hook
            $this->before();

            // Perform the actual state change
            $this->model->{$this->field} = $toStateName;
            $this->model->save();

            // Call after hook
            $this->after();

            /** @var StateContract $toState */
            $toState = $this->model->{$this->field};

            $result = TransitionResult::success(
                model: $this->model,
                fromState: $fromStateName,
                toState: $toState->name(),
                metadata: array_merge($this->metadata, [
                    'reason' => $this->reason,
                    'model_class' => $this->model::class,
                    'model_id' => $this->model->getKey(),
                ])
            );

            // Dispatch StateTransitioned event if events are enabled
            if ($this->eventsEnabled()) {
                event(new StateTransitioned(
                    model: $this->model,
                    field: $this->field,
                    fromState: $fromStateName,
                    toState: $toState->name(),
                    performer: auth()->user(),
                    reason: $this->reason,
                    metadata: $this->metadata,
                ));
            }

            // Record history if enabled and model has HasStateHistory trait
            $this->recordHistory($fromStateName, $toState->name());

            return $result;
        } catch (Throwable $e) {
            // Dispatch TransitionFailed event if events are enabled
            if ($this->eventsEnabled()) {
                event(new TransitionFailed(
                    model: $this->model,
                    field: $this->field,
                    fromState: $fromStateName,
                    toState: $toStateName,
                    error: $e->getMessage(),
                    exception: $e,
                    performer: auth()->user(),
                    reason: $this->reason,
                    metadata: $this->metadata,
                ));
            }

            throw $e;
        }
    }

    /**
     * Check if events feature is enabled.
     */
    protected function eventsEnabled(): bool
    {
        return config('laravel-stateflow.features.events', true);
    }

    /**
     * Check if history recording is enabled.
     */
    protected function historyEnabled(): bool
    {
        return config('laravel-stateflow.history.enabled', true)
            && config('laravel-stateflow.features.history', true);
    }

    /**
     * Record the state transition to history.
     *
     * This method gracefully handles cases where the history table doesn't exist,
     * making the feature backward-compatible and optional.
     */
    protected function recordHistory(string $fromState, string $toState): void
    {
        // Only record if history is enabled and model uses HasStateHistory trait
        if (! $this->historyEnabled()) {
            return;
        }

        if (! method_exists($this->model, 'stateHistory')) {
            return;
        }

        try {
            RecordStateTransition::make()->recordRaw(
                model: $this->model,
                field: $this->field,
                fromState: $fromState,
                toState: $toState,
                performer: auth()->user(),
                reason: $this->reason,
                metadata: $this->metadata,
                transitionClass: static::class,
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Silently ignore if history table doesn't exist
            // This allows the feature to be optional and backward-compatible
            if (! str_contains($e->getMessage(), 'state_histories')) {
                throw $e;
            }
        }
    }

    /**
     * Called before transition executes.
     *
     * Override in subclass for custom logic.
     */
    protected function before(): void
    {
        // Override in subclass
    }

    /**
     * Called after transition executes.
     *
     * Override in subclass for custom logic.
     */
    protected function after(): void
    {
        // Override in subclass
    }

    /**
     * Set the reason for the transition.
     */
    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Set metadata for the transition.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get the model.
     *
     * @return Model&HasStatesContract
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the field.
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Get the target state class.
     *
     * @return class-string<StateContract>
     */
    public function getTargetStateClass(): string
    {
        return $this->targetStateClass;
    }
}
