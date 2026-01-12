<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function () {
    config()->set('laravel-stateflow.features.events', true);
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('StateTransitioned Event', function () {
    /**
     * Scenario: Create StateTransitioned event with minimal parameters
     * Setup: Create Post and instantiate event after transition completes
     * Assertion: Event is created with required fields, optional fields are null
     */
    it('can be instantiated', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($event)->toBeInstanceOf(StateTransitioned::class)
            ->and($event->model)->toBe($post)
            ->and($event->field)->toBe('state')
            ->and($event->fromState)->toBe('draft')
            ->and($event->toState)->toBe('review')
            ->and($event->performer)->toBeNull()
            ->and($event->reason)->toBeNull()
            ->and($event->metadata)->toBeNull();
    });

    /**
     * Scenario: Create event with all optional parameters
     * Setup: Create Post and User, instantiate event with performer, reason, and metadata
     * Assertion: All optional fields are populated correctly
     */
    it('can be created with full parameters', function () {
        $this->createUsersTable();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => 'admin']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: $user,
            reason: 'Testing transition',
            metadata: ['test_key' => 'test_value'],
        );

        expect($event->performer)->toBe($user)
            ->and($event->reason)->toBe('Testing transition')
            ->and($event->metadata)->toBe(['test_key' => 'test_value']);
    });

    /**
     * Scenario: Create event using fromTransitionData factory method
     * Setup: Create TransitionData with state change details
     * Assertion: Event is created with data from TransitionData object
     */
    it('can be created from TransitionData', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $transitionData = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            reason: 'Via factory method',
        );

        $event = StateTransitioned::fromTransitionData($transitionData);

        expect($event)->toBeInstanceOf(StateTransitioned::class)
            ->and($event->model)->toBe($post)
            ->and($event->field)->toBe('state')
            ->and($event->fromState)->toBe('draft')
            ->and($event->toState)->toBe('review')
            ->and($event->reason)->toBe('Via factory method');
    });

    /**
     * Scenario: Create event using fromContext factory method
     * Setup: Create TransitionContext and pass additional context array
     * Assertion: Event is created with context data including custom context array
     */
    it('can be created from TransitionContext', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $transitionData = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($transitionData);

        $event = StateTransitioned::fromContext($context, ['history_id' => 42]);

        expect($event)->toBeInstanceOf(StateTransitioned::class)
            ->and($event->model)->toBe($post)
            ->and($event->field)->toBe('state')
            ->and($event->context)->toBe(['history_id' => 42]);
    });

    /**
     * Scenario: Access model information from event
     * Setup: Create event
     * Assertion: Helper methods return model, field, model class, and primary key
     */
    it('provides model information', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($event->getModel())->toBe($post)
            ->and($event->getField())->toBe('state')
            ->and($event->getModelClass())->toBe(Post::class)
            ->and($event->getModelKey())->toBe($post->id);
    });

    /**
     * Scenario: Retrieve history ID from event context
     * Setup: Create event with history_id in context array
     * Assertion: getHistoryId() returns the history_id value from context
     */
    it('retrieves history id from context', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            context: ['history_id' => 123],
        );

        expect($event->getHistoryId())->toBe(123);
    });

    /**
     * Scenario: Get history ID when context is empty
     * Setup: Create event without context
     * Assertion: getHistoryId() returns null
     */
    it('returns null when no history id in context', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($event->getHistoryId())->toBeNull();
    });

    /**
     * Scenario: Generate human-readable summary
     * Setup: Create event
     * Assertion: getSummary() contains model class and state names
     */
    it('generates a summary string', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        $summary = $event->getSummary();

        expect($summary)->toContain('Post')
            ->and($summary)->toContain('draft')
            ->and($summary)->toContain('review');
    });

    /**
     * Scenario: Convert event to array representation
     * Setup: Create event with all optional parameters
     * Assertion: toArray() returns array with all event data
     */
    it('converts to array', function () {
        $this->createUsersTable();
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => 'admin']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: $user,
            reason: 'Test reason',
            metadata: ['key' => 'value'],
            context: ['history_id' => 42],
        );

        $array = $event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKey('model_type', Post::class)
            ->and($array)->toHaveKey('model_id', $post->id)
            ->and($array)->toHaveKey('field', 'state')
            ->and($array)->toHaveKey('from_state', 'draft')
            ->and($array)->toHaveKey('to_state', 'review')
            ->and($array)->toHaveKey('performer_id', $user->id)
            ->and($array)->toHaveKey('reason', 'Test reason')
            ->and($array)->toHaveKey('metadata', ['key' => 'value']);
    });

    /**
     * Scenario: Verify event implements StateFlowEvent interface
     * Setup: Create event
     * Assertion: Event is instance of StateFlowEvent contract
     */
    it('implements StateFlowEvent interface', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $event = new StateTransitioned(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($event)->toBeInstanceOf(\Hpwebdeveloper\LaravelStateflow\Contracts\StateFlowEvent::class);
    });
});
