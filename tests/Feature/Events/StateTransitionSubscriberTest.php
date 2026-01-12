<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioned;
use Hpwebdeveloper\LaravelStateflow\Events\StateTransitioning;
use Hpwebdeveloper\LaravelStateflow\Events\TransitionFailed;
use Hpwebdeveloper\LaravelStateflow\Listeners\StateTransitionSubscriber;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config()->set('laravel-stateflow.features.events', true);
    config()->set('laravel-stateflow.events.subscriber_enabled', true);
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('StateTransitionSubscriber', function () {
    /**
     * Scenario: Create StateTransitionSubscriber instance
     * Setup: Instantiate subscriber
     * Assertion: Instance is of correct class
     */
    it('can be instantiated', function () {
        $subscriber = new StateTransitionSubscriber;

        expect($subscriber)->toBeInstanceOf(StateTransitionSubscriber::class);
    });

    /**
     * Scenario: Get event-to-handler mapping from subscriber
     * Setup: Create subscriber and mock dispatcher
     * Assertion: subscribe() returns correct event => handler method mapping
     */
    it('returns correct event mapping', function () {
        $subscriber = new StateTransitionSubscriber;
        $dispatcher = Mockery::mock(\Illuminate\Events\Dispatcher::class);

        $mapping = $subscriber->subscribe($dispatcher);

        expect($mapping)->toBe([
            StateTransitioning::class => 'handleTransitioning',
            StateTransitioned::class => 'handleTransitioned',
            TransitionFailed::class => 'handleFailed',
        ]);
    });

    describe('handleTransitioning', function () {
        /**
         * Scenario: Log StateTransitioning event
         * Setup: Spy on Log, create event, call handleTransitioning
         * Assertion: Log::info is called with message containing '[StateFlow]' and 'transitioning'
         */
        it('logs transitioning event', function () {
            Log::spy();

            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $event = new StateTransitioning(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'review',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleTransitioning($event);

            Log::shouldHaveReceived('info')
                ->withArgs(function ($message) {
                    return str_contains($message, '[StateFlow]')
                        && str_contains($message, 'transitioning');
                })
                ->once();
        });

        /**
         * Scenario: Logging disabled when subscriber_enabled is false
         * Setup: Disable subscriber in config, spy on Log, handle event
         * Assertion: Log::info is not called
         */
        it('does not log when subscriber_enabled is false', function () {
            config()->set('laravel-stateflow.events.subscriber_enabled', false);
            Log::spy();

            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $event = new StateTransitioning(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'review',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleTransitioning($event);

            Log::shouldNotHaveReceived('info');
        });

        /**
         * Scenario: Logging disabled when log_transitioning config is false
         * Setup: Disable log_transitioning, spy on Log, handle event
         * Assertion: Log::info is not called
         */
        it('does not log when log_transitioning is false', function () {
            config()->set('laravel-stateflow.events.log_transitioning', false);
            Log::spy();

            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $event = new StateTransitioning(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'review',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleTransitioning($event);

            Log::shouldNotHaveReceived('info');
        });
    });

    describe('handleTransitioned', function () {
        /**
         * Scenario: Log StateTransitioned event
         * Setup: Spy on Log, create event, call handleTransitioned
         * Assertion: Log::info is called with message containing '[StateFlow]' and 'transitioned'
         */
        it('logs transitioned event', function () {
            Log::spy();

            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            $event = new StateTransitioned(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'review',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleTransitioned($event);

            Log::shouldHaveReceived('info')
                ->withArgs(function ($message) {
                    return str_contains($message, '[StateFlow]')
                        && str_contains($message, 'transitioned');
                })
                ->once();
        });

        /**
         * Scenario: Logging disabled when log_transitioned config is false
         * Setup: Disable log_transitioned, spy on Log, handle event
         * Assertion: Log::info is not called
         */
        it('does not log when log_transitioned is false', function () {
            config()->set('laravel-stateflow.events.log_transitioned', false);
            Log::spy();

            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            $event = new StateTransitioned(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'review',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleTransitioned($event);

            Log::shouldNotHaveReceived('info');
        });
    });

    describe('handleFailed', function () {
        /**
         * Scenario: Log TransitionFailed event as error
         * Setup: Spy on Log, create failed event, call handleFailed
         * Assertion: Log::error is called with message containing '[StateFlow]' and 'Failed'
         */
        it('logs failed event as error', function () {
            Log::spy();

            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $event = new TransitionFailed(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'published',
                error: 'Transition not allowed',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleFailed($event);

            Log::shouldHaveReceived('error')
                ->withArgs(function ($message) {
                    return str_contains($message, '[StateFlow]')
                        && str_contains($message, 'Failed');
                })
                ->once();
        });

        /**
         * Scenario: Logging disabled when log_failed config is false
         * Setup: Disable log_failed, spy on Log, handle failed event
         * Assertion: Log::error is not called
         */
        it('does not log when log_failed is false', function () {
            config()->set('laravel-stateflow.events.log_failed', false);
            Log::spy();

            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $event = new TransitionFailed(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'published',
                error: 'Transition not allowed',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleFailed($event);

            Log::shouldNotHaveReceived('error');
        });
    });

    describe('custom log channel', function () {
        /**
         * Scenario: Use custom log channel configured in config
         * Setup: Set log_channel to 'daily', mock Log::channel(), create event
         * Assertion: Log::channel('daily') is called and info is logged to that channel
         */
        it('uses configured log channel', function () {
            config()->set('laravel-stateflow.events.log_channel', 'daily');

            $channelMock = Mockery::mock();
            $channelMock->shouldReceive('info')->once();

            Log::shouldReceive('channel')
                ->with('daily')
                ->once()
                ->andReturn($channelMock);

            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $event = new StateTransitioning(
                model: $post,
                field: 'state',
                fromState: 'draft',
                toState: 'review',
            );

            $subscriber = new StateTransitionSubscriber;
            $subscriber->handleTransitioning($event);
        });
    });
});
