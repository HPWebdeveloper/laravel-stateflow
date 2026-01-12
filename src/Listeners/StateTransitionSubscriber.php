<?php

declare(strict_types=1);

namespace Hpwebdeveloper\LaravelStateflow\Listeners;

use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Event subscriber for state transition events.
 *
 * This subscriber centralizes handling of all state transition events,
 * providing configurable logging and extensibility for custom handling.
 */
class StateTransitionSubscriber
{
    /**
     * Handle the StateTransitioning event.
     *
     * This is called BEFORE a transition occurs. Logging here captures
     * the intent to transition, regardless of whether it succeeds.
     */
    public function handleTransitioning(StateTransitioning $event): void
    {
        if (! $this->shouldLog('transitioning')) {
            return;
        }

        $this->log('info', $event->getSummary(), [
            'model_type' => $event->getModelClass(),
            'model_id' => $event->getModelKey(),
            'field' => $event->field,
            'from_state' => $event->fromState,
            'to_state' => $event->toState,
            'performer_id' => $event->performer?->getAuthIdentifier(),
            'reason' => $event->reason,
        ]);
    }

    /**
     * Handle the StateTransitioned event.
     *
     * This is called AFTER a successful transition. Use this for
     * logging, notifications, and post-transition side effects.
     */
    public function handleTransitioned(StateTransitioned $event): void
    {
        if (! $this->shouldLog('transitioned')) {
            return;
        }

        $this->log('info', $event->getSummary(), $event->toArray());
    }

    /**
     * Handle the TransitionFailed event.
     *
     * This is called when a transition fails. Critical for
     * error tracking and debugging.
     */
    public function handleFailed(TransitionFailed $event): void
    {
        if (! $this->shouldLog('failed')) {
            return;
        }

        $this->log('error', $event->getErrorSummary(), $event->toArray());
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            StateTransitioning::class => 'handleTransitioning',
            StateTransitioned::class => 'handleTransitioned',
            TransitionFailed::class => 'handleFailed',
        ];
    }

    /**
     * Check if logging is enabled for a specific event type.
     */
    protected function shouldLog(string $eventType): bool
    {
        // Check if subscriber logging is enabled globally
        if (! config('laravel-stateflow.events.subscriber_enabled', true)) {
            return false;
        }

        // Check specific event type
        return match ($eventType) {
            'transitioning' => config('laravel-stateflow.events.log_transitioning', true),
            'transitioned' => config('laravel-stateflow.events.log_transitioned', true),
            'failed' => config('laravel-stateflow.events.log_failed', true),
            default => true,
        };
    }

    /**
     * Log a message to the configured channel.
     *
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $channel = config('laravel-stateflow.events.log_channel');

        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->{$level}("[StateFlow] {$message}", $context);
    }
}
