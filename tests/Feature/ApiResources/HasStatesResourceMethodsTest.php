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

        it('accepts user context', function () {
            $user = new User(['id' => 1, 'role' => 'admin']);
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $resource = $post->getStateResource($user);

            expect($resource['name'])->toBe('draft');
        });

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
        it('returns state for UI display', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $ui = $post->getStateForUI();

            expect($ui)->toHaveKeys(['name', 'title', 'color', 'icon']);
            expect($ui['name'])->toBe('draft');
            expect($ui['title'])->toBe('Draft');
            expect($ui['color'])->toBe('primary');
        });

        it('returns null for no state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $post->setAttribute('state', null);

            $ui = $post->getStateForUI();

            expect($ui)->toBeNull();
        });
    });

    describe('getNextStatesForUI', function () {
        it('returns next states for UI display', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $nextStates = $post->getNextStatesForUI();

            expect($nextStates)->toBeArray();
            expect(count($nextStates))->toBeGreaterThan(0);

            $nextState = $nextStates[0];
            expect($nextState)->toHaveKeys(['name', 'title', 'color', 'icon']);
        });

        it('accepts user context', function () {
            $user = new User(['id' => 1, 'role' => 'admin']);
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $nextStates = $post->getNextStatesForUI($user);

            expect($nextStates)->toBeArray();
        });

        it('returns empty array when no transitions available', function () {
            // Published has no outgoing transitions
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            $nextStates = $post->getNextStatesForUI();

            expect($nextStates)->toBeArray();
            expect($nextStates)->toBeEmpty();
        });
    });
});
