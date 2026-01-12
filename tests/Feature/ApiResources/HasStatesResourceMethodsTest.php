<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;

beforeEach(function () {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('HasStates resource methods', function () {
    describe('getStateResource', function () {
        /**
         * Scenario: Model returns its current state as a resource array
         * Setup: Create post in draft state
         * Assertions: Resource contains all state metadata keys and correct values
         */
        it('returns state as resource array', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $resource = $post->getStateResource();

            expect($resource)->toHaveKeys([
                'name',
                'title',
                'color',
                'icon',
                'description',
                'is_default',
                'is_current',
                'can_transition_to',
                'metadata',
            ]);
            expect($resource['name'])->toBe('draft');
            expect($resource['title'])->toBe('Draft');
            expect($resource['is_current'])->toBeTrue();
        });

        /**
         * Scenario: State resource respects user context for permission checks
         * Setup: Create admin user and draft post
         * Assertions: Resource generated successfully with user context
         */
        it('accepts user context', function () {
            $user = new User(['id' => 1, 'role' => 'admin']);
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $resource = $post->getStateResource($user);

            expect($resource['name'])->toBe('draft');
        });

        /**
         * Scenario: Resource handles models with null state gracefully
         * Setup: Create post and manually set state to null
         * Assertions: Resource returns with name key present
         */
        it('handles null state', function () {
            // Create post without triggering the creating hook
            $post = new Post(['id' => 999, 'title' => 'No State']);
            $post->exists = true;

            // Need to create a real post and then clear its state
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $post->setAttribute('state', null);

            $resource = $post->getStateResource();

            expect($resource)->toHaveKey('name');
        });
    });

    describe('getStateForUI', function () {
        /**
         * Scenario: Model returns simplified state data for UI rendering
         * Setup: Create post in draft state
         * Assertions: UI data contains name, title, color, icon keys with correct values
         */
        it('returns state for UI display', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $ui = $post->getStateForUI();

            expect($ui)->toHaveKeys(['name', 'title', 'color', 'icon']);
            expect($ui['name'])->toBe('draft');
            expect($ui['title'])->toBe('Draft');
            expect($ui['color'])->toBe('primary');
        });

        /**
         * Scenario: UI method returns null when model has no state
         * Setup: Create post and clear its state attribute
         * Assertions: getStateForUI returns null
         */
        it('returns null for no state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $post->setAttribute('state', null);

            $ui = $post->getStateForUI();

            expect($ui)->toBeNull();
        });
    });

    describe('getNextStatesForUI', function () {
        /**
         * Scenario: Model returns available transitions formatted for UI
         * Setup: Create post in draft state (has transitions)
         * Assertions: Array of next states with UI metadata (name, title, color, icon)
         */
        it('returns next states for UI display', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $nextStates = $post->getNextStatesForUI();

            expect($nextStates)->toBeArray();
            expect(count($nextStates))->toBeGreaterThan(0);

            $nextState = $nextStates[0];
            expect($nextState)->toHaveKeys(['name', 'title', 'color', 'icon']);
        });

        /**
         * Scenario: Next states are filtered by user permissions
         * Setup: Create admin user and draft post
         * Assertions: Next states array respects user context
         */
        it('accepts user context', function () {
            $user = new User(['id' => 1, 'role' => 'admin']);
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $nextStates = $post->getNextStatesForUI($user);

            expect($nextStates)->toBeArray();
        });

        /**
         * Scenario: Terminal states return no available transitions
         * Setup: Create post in published state (terminal state with no outgoing transitions)
         * Assertions: Empty array returned
         */
        it('returns empty array when no transitions available', function () {
            // Published has no outgoing transitions
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            $nextStates = $post->getNextStatesForUI();

            expect($nextStates)->toBeArray();
            expect($nextStates)->toBeEmpty();
        });
    });
});
