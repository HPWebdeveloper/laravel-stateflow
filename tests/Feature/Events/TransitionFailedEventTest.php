<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;

beforeEach(function () {
    config()->set('laravel-stateflow.features.events', true);
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('TransitionFailed Event', function () {
    /**
     * Scenario: Create TransitionFailed event with minimal parameters
     * Setup: Create Post and instantiate event with required fields only
     * Assertion: Event is created with provided values and nulls for optional fields
     */
    it('can be instantiated', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Transition not allowed',
        );

        expect($event)->toBeInstanceOf(TransitionFailed::class)
            ->and($event->model)->toBe($post)
            ->and($event->field)->toBe('state')
            ->and($event->fromState)->toBe('draft')
            ->and($event->toState)->toBe('published')
            ->and($event->error)->toBe('Transition not allowed')
            ->and($event->errorCode)->toBeNull()
            ->and($event->exception)->toBeNull()
            ->and($event->performer)->toBeNull();
    });

    /**
     * Scenario: Create TransitionFailed event with all optional parameters
     * Setup: Create Post, User, exception, and instantiate event with complete data
     * Assertion: All optional fields (errorCode, exception, performer, reason, metadata) are set correctly
     */
    it('can be created with full parameters', function () {
        $this->createUsersTable();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $exception = new RuntimeException('Something went wrong');
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com', 'role' => 'admin']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Transition failed',
            errorCode: 'ERR_001',
            exception: $exception,
            performer: $user,
            reason: 'Testing failure',
            metadata: ['test_key' => 'test_value'],
        );

        expect($event->errorCode)->toBe('ERR_001')
            ->and($event->exception)->toBe($exception)
            ->and($event->performer)->toBe($user)
            ->and($event->reason)->toBe('Testing failure')
            ->and($event->metadata)->toBe(['test_key' => 'test_value']);
    });

    /**
     * Scenario: Create event using fromTransitionData factory method
     * Setup: Create TransitionData and exception, use factory method with error details
     * Assertion: Event is created with data from TransitionData plus error, errorCode, and exception
     */
    it('can be created from TransitionData', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transitionData = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Published::class,
            reason: 'Via factory method',
        );

        $exception = new RuntimeException('Test exception');

        $event = TransitionFailed::fromTransitionData(
            $transitionData,
            'Transition not allowed',
            'NOT_ALLOWED',
            $exception
        );

        expect($event)->toBeInstanceOf(TransitionFailed::class)
            ->and($event->model)->toBe($post)
            ->and($event->field)->toBe('state')
            ->and($event->fromState)->toBe('draft')
            ->and($event->toState)->toBe('published')
            ->and($event->error)->toBe('Transition not allowed')
            ->and($event->errorCode)->toBe('NOT_ALLOWED')
            ->and($event->exception)->toBe($exception)
            ->and($event->reason)->toBe('Via factory method');
    });

    /**
     * Scenario: Create event using fromContext factory method
     * Setup: Create TransitionContext from TransitionData, use factory method
     * Assertion: Event is created with context data plus error and errorCode
     */
    it('can be created from TransitionContext', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transitionData = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Published::class,
        );

        $context = TransitionContext::fromTransitionData($transitionData);

        $event = TransitionFailed::fromContext(
            $context,
            'Authorization failed',
            'UNAUTHORIZED',
        );

        expect($event)->toBeInstanceOf(TransitionFailed::class)
            ->and($event->model)->toBe($post)
            ->and($event->field)->toBe('state')
            ->and($event->error)->toBe('Authorization failed')
            ->and($event->errorCode)->toBe('UNAUTHORIZED');
    });

    /**
     * Scenario: Access model information from event
     * Setup: Create event with Post model
     * Assertion: Helper methods return correct model, field, class, and key
     */
    it('provides model information', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Test error',
        );

        expect($event->getModel())->toBe($post)
            ->and($event->getField())->toBe('state')
            ->and($event->getModelClass())->toBe(Post::class)
            ->and($event->getModelKey())->toBe($post->id);
    });

    /**
     * Scenario: Check if event has an exception
     * Setup: Create two events - one without exception, one with exception
     * Assertion: hasException() returns false for event without, true for event with exception
     */
    it('checks for exception presence', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $eventWithoutException = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Test error',
        );

        $eventWithException = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Test error',
            exception: new RuntimeException('Oops'),
        );

        expect($eventWithoutException->hasException())->toBeFalse()
            ->and($eventWithException->hasException())->toBeTrue();
    });

    /**
     * Scenario: Retrieve exception message from event
     * Setup: Create event with RuntimeException
     * Assertion: getExceptionMessage() returns the exception's message text
     */
    it('returns exception message', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $exception = new RuntimeException('Database connection lost');

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Connection error',
            exception: $exception,
        );

        expect($event->getExceptionMessage())->toBe('Database connection lost');
    });

    /**
     * Scenario: Get exception message when no exception exists
     * Setup: Create event without exception
     * Assertion: getExceptionMessage() returns null
     */
    it('returns null for exception message when no exception', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Test error',
        );

        expect($event->getExceptionMessage())->toBeNull();
    });

    /**
     * Scenario: Generate human-readable error summary
     * Setup: Create event with error message
     * Assertion: getErrorSummary() includes model class, states, and error message
     */
    it('generates error summary', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Transition not permitted',
        );

        $summary = $event->getErrorSummary();

        expect($summary)->toContain('Post')
            ->and($summary)->toContain('draft')
            ->and($summary)->toContain('published')
            ->and($summary)->toContain('Transition not permitted');
    });

    /**
     * Scenario: Error summary includes error code when provided
     * Setup: Create event with error and errorCode
     * Assertion: Summary contains formatted error code [VALIDATION_ERROR]
     */
    it('includes error code in summary', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Validation failed',
            errorCode: 'VALIDATION_ERROR',
        );

        $summary = $event->getErrorSummary();

        expect($summary)->toContain('[VALIDATION_ERROR]');
    });

    /**
     * Scenario: Convert event to array representation
     * Setup: Create event with all parameters (performer, metadata, exception, etc.)
     * Assertion: toArray() returns array with all event data including exception class/message
     */
    it('converts to array', function () {
        $this->createUsersTable();
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $exception = new RuntimeException('Test exception');
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com', 'role' => 'admin']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Test error',
            errorCode: 'TEST_CODE',
            exception: $exception,
            performer: $user,
            reason: 'Test reason',
            metadata: ['key' => 'value'],
        );

        $array = $event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKey('model_type', Post::class)
            ->and($array)->toHaveKey('model_id', $post->id)
            ->and($array)->toHaveKey('field', 'state')
            ->and($array)->toHaveKey('from_state', 'draft')
            ->and($array)->toHaveKey('to_state', 'published')
            ->and($array)->toHaveKey('error', 'Test error')
            ->and($array)->toHaveKey('error_code', 'TEST_CODE')
            ->and($array)->toHaveKey('exception_class', RuntimeException::class)
            ->and($array)->toHaveKey('exception_message', 'Test exception')
            ->and($array)->toHaveKey('performer_id', $user->id)
            ->and($array)->toHaveKey('reason', 'Test reason')
            ->and($array)->toHaveKey('metadata', ['key' => 'value']);
    });

    /**
     * Scenario: Verify event implements StateFlowEvent interface
     * Setup: Create event instance
     * Assertion: Event is instance of StateFlowEvent contract
     */
    it('implements StateFlowEvent interface', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $event = new TransitionFailed(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'published',
            error: 'Test error',
        );

        expect($event)->toBeInstanceOf(\Hpwebdeveloper\LaravelStateflow\Contracts\StateFlowEvent::class);
    });
});
