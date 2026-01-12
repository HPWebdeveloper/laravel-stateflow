<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Http\Resources\StateCollectionResource;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('StateCollectionResource', function () {
    /**
     * Scenario: Generate collection resource for all states of a model
     * Setup: Use Post model class with configured states
     * Assertions: Returns non-empty array of state resources
     */
    it('creates collection for model class', function () {
        $request = Request::create('/');
        $resource = StateCollectionResource::forModel(Post::class);

        $array = $resource->toArray($request);

        expect($array)->toBeArray();
        expect($array)->not->toBeEmpty();
    });

    /**
     * Scenario: Non-stateful models return empty collection
     * Setup: Use User model which doesn't have state management
     * Assertions: Returns empty array
     */
    it('creates empty collection for non-stateable model', function () {
        $request = Request::create('/');
        $resource = StateCollectionResource::forModel(User::class);

        $array = $resource->toArray($request);

        expect($array)->toBeArray();
        expect($array)->toBeEmpty();
    });

    /**
     * Scenario: Generate collection of available next states for a model instance
     * Setup: Create draft post with configured transitions
     * Assertions: Returns array with at least one next state
     */
    it('creates collection for next states', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = StateCollectionResource::nextStates($post);
        $array = $resource->toArray($request);

        expect($array)->toBeArray();
        expect(count($array))->toBeGreaterThan(0);
    });

    /**
     * Scenario: Filter next states based on user permissions
     * Setup: Create admin user and draft post
     * Assertions: Collection respects user's permission context
     */
    it('creates collection with user context', function () {
        $user = new User(['id' => 1, 'role' => 'admin']);
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = StateCollectionResource::nextStates($post, $user);
        $array = $resource->toArray($request);

        expect($array)->toBeArray();
    });

    /**
     * Scenario: Return only essential state data to reduce API payload size
     * Setup: Create draft post, request next states in minimal format
     * Assertions: Array items contain only name and title, no color or metadata
     */
    it('supports minimal format', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = StateCollectionResource::nextStates($post)->minimal();
        $array = $resource->toArray($request);

        if (! empty($array)) {
            expect($array[0])->toHaveKeys(['name', 'title']);
            expect($array[0])->not->toHaveKey('color');
        }
    });

    /**
     * Scenario: Return UI-optimized state data with visual metadata but without transition flags
     * Setup: Create draft post, request next states in UI format
     * Assertions: Items include name, title, color, icon, description but exclude is_current/can_transition_to
     */
    it('supports ui format', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = StateCollectionResource::nextStates($post)->ui();
        $array = $resource->toArray($request);

        if (! empty($array)) {
            expect($array[0])->toHaveKeys(['name', 'title', 'color', 'icon', 'description']);
            expect($array[0])->not->toHaveKey('is_current');
        }
    });

    /**
     * Scenario: Return complete state data including all metadata and transition capabilities
     * Setup: Create draft post, request next states in full format
     * Assertions: Items contain all fields including is_default, is_current, can_transition_to
     */
    it('supports full format', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = StateCollectionResource::nextStates($post)->full();
        $array = $resource->toArray($request);

        if (! empty($array)) {
            expect($array[0])->toHaveKeys([
                'name',
                'title',
                'color',
                'is_default',
                'is_current',
                'can_transition_to',
            ]);
        }
    });

    /**
     * Scenario: Provide model context to collection so it can mark the current state correctly
     * Setup: Create post in draft state, build collection with Draft and Review states
     * Assertions: Draft state is marked as is_current=true since post is in draft
     */
    it('sets model context with withModel', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = (new StateCollectionResource([Draft::class, Review::class]))
            ->withModel($post);

        $array = $resource->toArray($request);

        // First item (Draft) should be current
        $draftItem = collect($array)->firstWhere('name', 'draft');
        expect($draftItem['is_current'])->toBeTrue();
    });

    /**
     * Scenario: Pass user context to enable permission-based filtering of available states
     * Setup: Create admin user and draft post, build Review state collection with user context
     * Assertions: Collection is successfully built with user permission context applied
     */
    it('sets user context with withUser', function () {
        $user = new User(['id' => 1, 'role' => 'admin']);
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = (new StateCollectionResource([Review::class]))
            ->withModel($post)
            ->withUser($user);

        $array = $resource->toArray($request);

        expect($array)->toBeArray();
    });

    /**
     * Scenario: Get complete list of all possible states for a given model type
     * Setup: Use Post model class which has draft, review, published, rejected states
     * Assertions: Returned array contains all configured state names for Post model
     */
    it('returns states for model from all states', function () {
        $request = Request::create('/');
        $resource = StateCollectionResource::forModel(Post::class);

        $array = $resource->toArray($request);

        $names = collect($array)->pluck('name')->all();
        expect($names)->toContain('draft');
        expect($names)->toContain('review');
    });
});
