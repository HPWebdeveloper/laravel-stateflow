<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Actions;

use Hpwebdeveloper\LaravelStateflow\Actions\Concerns\AsAction;
use Hpwebdeveloper\LaravelStateflow\DTOs\StateHistoryData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Events\StateHistoryRecorded;
use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Action to record a state transition in history.
 *
 * Follows Single Responsibility Principle - only handles history recording.
 * Can be called statically or as an instance.
 *
 * @example
 * // From TransitionContext
 * RecordStateTransition::run($context);
 *
 * // From StateHistoryData
 * RecordStateTransition::make()->recordFromData($historyData);
 *
 * // Raw parameters
 * RecordStateTransition::make()->recordRaw($model, 'state', 'draft', 'review');
 */
class RecordStateTransition
{
    use AsAction;

    /**
     * Record a state transition from TransitionContext.
     */
    public function handle(TransitionContext $context): ?StateHistory
    {
        if (! $this->isHistoryEnabled()) {
            return null;
        }

        $historyData = StateHistoryData::fromTransitionContextWithRequest($context);

        return $this->recordFromData($historyData);
    }

    /**
     * Record from TransitionData and optional result.
     */
    public function fromTransitionData(TransitionData $data, ?TransitionResult $result = null): ?StateHistory
    {
        if (! $this->isHistoryEnabled()) {
            return null;
        }

        $historyData = StateHistoryData::fromTransitionData($data, $result);

        return $this->recordFromData($historyData);
    }

    /**
     * Record from StateHistoryData DTO.
     */
    public function recordFromData(StateHistoryData $data): StateHistory
    {
        $modelClass = $this->getHistoryModelClass();

        /** @var StateHistory $history */
        $history = $modelClass::create($data->toArray());

        // Dispatch event if configured
        if ($this->shouldDispatchEvents()) {
            event(new StateHistoryRecorded($history));
        }

        return $history;
    }

    /**
     * Record with raw parameters (convenience method).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordRaw(
        Model $model,
        string $field,
        string $fromState,
        string $toState,
        ?Authenticatable $performer = null,
        ?string $reason = null,
        ?array $metadata = null,
        ?string $transitionClass = null
    ): ?StateHistory {
        if (! $this->isHistoryEnabled()) {
            return null;
        }

        $data = new StateHistoryData(
            model: $model,
            field: $field,
            fromState: $fromState,
            toState: $toState,
            performer: $performer,
            reason: $reason,
            metadata: $metadata,
            transitionClass: $transitionClass,
        );

        return $this->recordFromData($data);
    }

    /**
     * Check if history recording is enabled.
     */
    protected function isHistoryEnabled(): bool
    {
        return config('laravel-stateflow.history.enabled', true)
            && config('laravel-stateflow.features.history', true);
    }

    /**
     * Check if events should be dispatched.
     */
    protected function shouldDispatchEvents(): bool
    {
        return config('laravel-stateflow.history.dispatch_events', false);
    }

    /**
     * Get the history model class.
     *
     * @return class-string<StateHistory>
     */
    protected function getHistoryModelClass(): string
    {
        return config('laravel-stateflow.history.model', StateHistory::class);
    }
}
