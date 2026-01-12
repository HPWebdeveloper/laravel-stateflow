<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    $this->createUsersTable();
    Post::resetStateRegistration();
    config()->set('laravel-stateflow.features.events', true);
});

// ============================================================================
// EVENTS AND HISTORY INTEGRATION
// ============================================================================

describe('Events and History Integration', function (): void {

    /**
     * Scenario: Both history and events work together during transition
     * Setup: Fake events, create Post, transition to Review
     * Assertion: Both events dispatched AND history record created
     */
    it('records history and dispatches events together', function (): void {
        Event::fake([StateTransitioning::class, StateTransitioned::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo(Review::class);

        // Events were dispatched
        Event::assertDispatched(StateTransitioning::class);
        Event::assertDispatched(StateTransitioned::class);

        // History was recorded (even with faked events)
        expect(StateHistory::count())->toBe(1);
    });

    /**
     * Scenario: History not recorded when event listener cancels transition
     * Setup: Listen to StateTransitioning and cancel, attempt transition
     * Assertion: No history record created and model remains in original state
     */
    it('does not record history when event cancels transition', function (): void {
        Event::listen(StateTransitioning::class, function ($event) {
            $event->cancel('Business rule violated');
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        try {
            $post->transitionTo(Review::class);
        } catch (\Throwable $e) {
            // Expected
        }

        // History should NOT be recorded because transition was cancelled
        expect(StateHistory::count())->toBe(0);
        expect($post->fresh()->state->name())->toBe('draft');
    });

    /**
     * Scenario: Metadata is shared between history and events
     * Setup: Listen to event to capture metadata, transition with metadata
     * Assertion: Both history record and event contain the same metadata
     */
    it('records history with event metadata', function (): void {
        $capturedMetadata = null;

        Event::listen(StateTransitioned::class, function ($event) use (&$capturedMetadata) {
            $capturedMetadata = $event->metadata;
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo(Review::class, metadata: ['important' => true]);

        // History recorded
        $history = StateHistory::first();
        expect($history->metadata)->toBe(['important' => true]);

        // Event also has metadata
        expect($capturedMetadata)->toBe(['important' => true]);
    });

    /**
     * Scenario: Authenticated user is tracked in both history and events
     * Setup: Auth as user, listen to event, transition
     * Assertion: Both history and event have performer_id matching authenticated user
     */
    it('records history with authenticated user from event', function (): void {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => 'admin']);
        $this->actingAs($user);

        $capturedPerformer = null;

        Event::listen(StateTransitioned::class, function ($event) use (&$capturedPerformer) {
            $capturedPerformer = $event->performer;
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo(Review::class);

        // History has performer
        $history = StateHistory::first();
        expect($history->performer_id)->toBe($user->id);

        // Event also has performer
        expect($capturedPerformer?->id)->toBe($user->id);
    });

});

// ============================================================================
// EVENT PROPERTIES
// ============================================================================

describe('Event Properties', function (): void {

    /**
     * Scenario: StateTransitioned event contains all transition details
     * Setup: Listen to event, auth as user, transition with reason and metadata
     * Assertion: Event has correct model, field, states, performer, reason, and metadata
     */
    it('StateTransitioned event has correct properties', function (): void {
        $capturedEvent = null;

        Event::listen(StateTransitioned::class, function ($event) use (&$capturedEvent) {
            $capturedEvent = $event;
        });

        $user = User::create(['name' => 'Test', 'email' => 'test@example.com']);
        $this->actingAs($user);

        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);
        $post->transitionTo(Review::class, reason: 'Ready for review', metadata: ['priority' => 'high']);

        expect($capturedEvent)->not->toBeNull();
        expect($capturedEvent->model->id)->toBe($post->id);
        expect($capturedEvent->field)->toBe('state');
        expect($capturedEvent->fromState)->toBe('draft');
        expect($capturedEvent->toState)->toBe('review');
        expect($capturedEvent->performer?->id)->toBe($user->id);
        expect($capturedEvent->reason)->toBe('Ready for review');
        expect($capturedEvent->metadata)->toBe(['priority' => 'high']);
    });

    /**
     * Scenario: StateTransitioning event contains pre-transition details
     * Setup: Listen to event, create Post, transition with reason
     * Assertion: Event has correct fromState, toState, and reason before transition completes
     */
    it('StateTransitioning event has correct properties', function (): void {
        $capturedEvent = null;

        Event::listen(StateTransitioning::class, function ($event) use (&$capturedEvent) {
            $capturedEvent = $event;
        });

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo(Review::class, reason: 'Starting review');

        expect($capturedEvent)->not->toBeNull();
        expect($capturedEvent->fromState)->toBe('draft');
        expect($capturedEvent->toState)->toBe('review');
        expect($capturedEvent->reason)->toBe('Starting review');
    });

    /**
     * Scenario: TransitionFailed event dispatched for invalid transition
     * Setup: Fake TransitionFailed event, attempt disallowed transition (draft to published)
     * Assertion: Event is dispatched with correct errorCode and state information
     */
    it('TransitionFailed event is dispatched for invalid transition', function (): void {
        Event::fake([TransitionFailed::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        try {
            // Direct draft -> published is not allowed
            $post->transitionTo(Published::class);
        } catch (\Throwable $e) {
            // Expected
        }

        Event::assertDispatched(TransitionFailed::class, function ($event) {
            return $event->errorCode === 'TRANSITION_NOT_ALLOWED'
                && $event->fromState === 'draft'
                && $event->toState === 'published';
        });
    });

});

// ============================================================================
// EVENTS DISABLED
// ============================================================================

describe('Events Disabled', function (): void {

    /**
     * Scenario: History still records when events feature is disabled
     * Setup: Disable events in config, fake events, execute transition
     * Assertion: Events NOT dispatched but history IS still recorded
     */
    it('does not dispatch events when disabled but still records history', function (): void {
        config()->set('laravel-stateflow.features.events', false);

        Event::fake([StateTransitioning::class, StateTransitioned::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->transitionTo(Review::class);

        // Events NOT dispatched
        Event::assertNotDispatched(StateTransitioning::class);
        Event::assertNotDispatched(StateTransitioned::class);

        // But history is still recorded
        expect(StateHistory::count())->toBe(1);
    });

});

// ============================================================================
// FORCE TRANSITION EVENTS
// ============================================================================

describe('Force Transition Events and History', function (): void {

    /**
     * Scenario: Force transition records history entry
     * Setup: Create Post, force transition to Published (normally not allowed)
     * Assertion: History is recorded with correct states and reason
     */
    it('force transition records history', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Force direct transition (normally not allowed)
        $post->forceTransitionTo(Published::class, reason: 'Admin override');

        expect(StateHistory::count())->toBe(1);

        $history = StateHistory::first();
        expect($history->from_state)->toBe('draft');
        expect($history->to_state)->toBe('published');
        expect($history->reason)->toBe('Admin override');
    });

    /**
     * Scenario: Force transition dispatches events like normal transition
     * Setup: Fake events, force transition
     * Assertion: Both StateTransitioning and StateTransitioned are dispatched
     */
    it('force transition dispatches events', function (): void {
        Event::fake([StateTransitioning::class, StateTransitioned::class]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $post->forceTransitionTo(Published::class);

        Event::assertDispatched(StateTransitioning::class);
        Event::assertDispatched(StateTransitioned::class);
    });

});
