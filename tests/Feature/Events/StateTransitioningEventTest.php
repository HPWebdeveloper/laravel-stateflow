<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function () {
    config()->set('laravel-stateflow.features.events', true);
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('StateTransitioning Event', function () {
    /**
     * Scenario: Create StateTransitioning event with minimal parameters
     * Setup: Create Post in draft, instantiate event before transition occurs
     * Assertion: Event is created with required fields, optional fields are null
     */
    it('can be instantiated', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new StateTransitioning(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($event)->toBeInstanceOf(StateTransitioning::class)
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
     * Setup: Create Post and User, instantiate event with performer, reason, metadata
     * Assertion: All optional fields are populated correctly
     */
    it('can be created with full parameters', function () {
        $this->createUsersTable();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => 'admin']);

        $event = new StateTransitioning(
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
     * Setup: Create TransitionData, use factory to create event
     * Assertion: Event is created with data from TransitionData
     */
    it('can be created from TransitionData', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transitionData = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            reason: 'Via factory method',
        );

        $event = StateTransitioning::fromTransitionData($transitionData);

        expect($event)->toBeInstanceOf(StateTransitioning::class)
            ->and($event->model)->toBe($post)
            ->and($event->field)->toBe('state')
            ->and($event->fromState)->toBe('draft')
            ->and($event->toState)->toBe('review')
            ->and($event->reason)->toBe('Via factory method');
    });

    /**
     * Scenario: Check default cancellation state
     * Setup: Create event
     * Assertion: Event is not cancelled by default, all cancellation flags are false/null
     */
    it('is not cancelled by default', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new StateTransitioning(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($event->shouldCancel)->toBeFalse()
            ->and($event->isCancelled())->toBeFalse()
            ->and($event->cancellationReason)->toBeNull();
    });

    /**
     * Scenario: Cancel transition with a reason
     * Setup: Create event, call cancel() with reason
     * Assertion: Cancellation flags are true, reason is stored
     */
    it('can be cancelled', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new StateTransitioning(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        $event->cancel('Business rule violated');

        expect($event->shouldCancel)->toBeTrue()
            ->and($event->isCancelled())->toBeTrue()
            ->and($event->cancellationReason)->toBe('Business rule violated');
    });

    /**
     * Scenario: Cancel transition without providing a reason
     * Setup: Create event, call cancel() without parameters
     * Assertion: Cancellation flags are true, reason is null
     */
    it('can be cancelled without reason', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new StateTransitioning(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        $event->cancel();

        expect($event->shouldCancel)->toBeTrue()
            ->and($event->isCancelled())->toBeTrue()
            ->and($event->cancellationReason)->toBeNull();
    });

    /**
     * Scenario: Access model information from event
     * Setup: Create event
     * Assertion: Helper methods return model, field, class, and key
     */
    it('provides model information', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new StateTransitioning(
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
     * Scenario: Generate human-readable summary
     * Setup: Create event
     * Assertion: getSummary() contains model class and state names
     */
    it('generates a summary string', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new StateTransitioning(
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
     * Scenario: Verify event implements StateFlowEvent interface
     * Setup: Create event
     * Assertion: Event is instance of StateFlowEvent contract
     */
    it('implements StateFlowEvent interface', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new StateTransitioning(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($event)->toBeInstanceOf(\Hpwebdeveloper\LaravelStateflow\Contracts\StateFlowEvent::class);
    });
});
