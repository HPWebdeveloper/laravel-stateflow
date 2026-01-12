<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionException;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionNotAllowedException;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config()->set('laravel-stateflow.features.events', true);
    config()->set('laravel-stateflow.features.permissions', false);
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('Event Dispatching Integration', function () {
    /**
     * Scenario: StateTransitioning event is dispatched before transition executes
     * Setup: Fake events, create Post, transition to review
     * Assertion: StateTransitioning event is dispatched with correct fromState and toState
     */
    it('dispatches StateTransitioning event before transition', function () {
        Event::fake([StateTransitioning::class, StateTransitioned::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Draft -> Review is an allowed transition
        $post->transitionTo('review');

        Event::assertDispatched(StateTransitioning::class, function ($event) use ($post) {
            return $event->model->id === $post->id
                && $event->fromState === 'draft'
                && $event->toState === 'review';
        });
    });

    /**
     * Scenario: StateTransitioned event is dispatched after successful transition
     * Setup: Fake events, create Post, execute transition
     * Assertion: StateTransitioned event is dispatched with correct state data
     */
    it('dispatches StateTransitioned event after successful transition', function () {
        Event::fake([StateTransitioning::class, StateTransitioned::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $post->transitionTo('review');

        Event::assertDispatched(StateTransitioned::class, function ($event) use ($post) {
            return $event->model->id === $post->id
                && $event->fromState === 'draft'
                && $event->toState === 'review';
        });
    });

    /**
     * Scenario: Events are dispatched in correct order (transitioning then transitioned)
     * Setup: Listen to both events and track order, execute transition
     * Assertion: Events array contains ['transitioning', 'transitioned'] in that order
     */
    it('dispatches both events in correct order', function () {
        $events = [];

        Event::listen(StateTransitioning::class, function ($event) use (&$events) {
            $events[] = 'transitioning';
        });

        Event::listen(StateTransitioned::class, function ($event) use (&$events) {
            $events[] = 'transitioned';
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo('review');

        expect($events)->toBe(['transitioning', 'transitioned']);
    });

    /**
     * Scenario: Transition can be cancelled via event listener
     * Setup: Listen to StateTransitioning and call cancel() with reason
     * Assertion: Transition throws exception with cancel reason, model state remains draft
     */
    it('allows cancelling transition via event', function () {
        Event::listen(StateTransitioning::class, function ($event) {
            $event->cancel('Business rule violated');
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect(fn () => $post->transitionTo('review'))
            ->toThrow(TransitionException::class, 'Business rule violated');

        expect($post->fresh()->state->name())->toBe('draft');
    });

    /**
     * Scenario: Cancel transition without providing a reason
     * Setup: Listen and call cancel() without parameters
     * Assertion: Throws exception with default 'Cancelled by listener' message
     */
    it('allows cancelling transition without reason', function () {
        Event::listen(StateTransitioning::class, function ($event) {
            $event->cancel();
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect(fn () => $post->transitionTo('review'))
            ->toThrow(TransitionException::class, 'Cancelled by listener');

        expect($post->fresh()->state->name())->toBe('draft');
    });

    /**
     * Scenario: StateTransitioned is not dispatched when transition is cancelled
     * Setup: Fake StateTransitioned, cancel transition in listener
     * Assertion: StateTransitioned event is never dispatched
     */
    it('does not dispatch StateTransitioned when transition is cancelled', function () {
        Event::fake([StateTransitioned::class]);

        Event::listen(StateTransitioning::class, function ($event) {
            $event->cancel('Cancelled');
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        try {
            $post->transitionTo('review');
        } catch (TransitionException $e) {
            // Expected
        }

        Event::assertNotDispatched(StateTransitioned::class);
    });

    /**
     * Scenario: Events are not dispatched when feature is disabled
     * Setup: Disable events feature in config, fake events, execute transition
     * Assertion: Neither StateTransitioning nor StateTransitioned is dispatched
     */
    it('does not dispatch events when feature is disabled', function () {
        config()->set('laravel-stateflow.features.events', false);

        Event::fake([StateTransitioning::class, StateTransitioned::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo('review');

        Event::assertNotDispatched(StateTransitioning::class);
        Event::assertNotDispatched(StateTransitioned::class);
    });

    /**
     * Scenario: Capture event metadata from listener without affecting transition
     * Setup: Listen to StateTransitioning, capture metadata, transition with custom metadata
     * Assertion: Transition succeeds and captured metadata matches provided metadata
     */
    it('can capture event metadata without affecting transition', function () {
        $capturedMetadata = null;

        Event::listen(StateTransitioning::class, function ($event) use (&$capturedMetadata) {
            $capturedMetadata = $event->metadata;
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo('review', metadata: ['custom_key' => 'custom_value']);

        expect($post->fresh()->state->name())->toBe('review')
            ->and($capturedMetadata)->toBe(['custom_key' => 'custom_value']);
    });

    /**
     * Scenario: Reason parameter is included in events
     * Setup: Listen to StateTransitioned, capture reason, transition with reason
     * Assertion: Captured reason matches provided reason
     */
    it('includes reason in events when provided', function () {
        $capturedReason = null;

        Event::listen(StateTransitioned::class, function ($event) use (&$capturedReason) {
            $capturedReason = $event->reason;
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo('review', reason: 'Customer requested');

        expect($capturedReason)->toBe('Customer requested');
    });

    /**
     * Scenario: TransitionFailed event is dispatched on invalid transition
     * Setup: Fake TransitionFailed event, attempt disallowed transition (draft to published)
     * Assertion: TransitionFailed is dispatched with correct fromState and toState
     */
    it('dispatches TransitionFailed event on invalid transition', function () {
        Event::fake([TransitionFailed::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        try {
            // Attempt invalid transition (draft to published - not allowed directly)
            $post->transitionTo('published');
        } catch (TransitionNotAllowedException $e) {
            // Expected
        }

        Event::assertDispatched(TransitionFailed::class, function ($event) use ($post) {
            return $event->model->id === $post->id
                && $event->fromState === 'draft'
                && $event->toState === 'published';
        });
    });
});
