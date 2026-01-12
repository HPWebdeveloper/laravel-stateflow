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
    it('creates collection for model class', function () {
        $request = Request::create('/');
        $resource = StateCollectionResource::forModel(Post::class);

        $array = $resource->toArray($request);

        expect($array)->toBeArray();
        expect($array)->not->toBeEmpty();
    });

    it('creates empty collection for non-stateable model', function () {
        $request = Request::create('/');
        $resource = StateCollectionResource::forModel(User::class);

        $array = $resource->toArray($request);

        expect($array)->toBeArray();
        expect($array)->toBeEmpty();
    });

    it('creates collection for next states', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = StateCollectionResource::nextStates($post);
        $array = $resource->toArray($request);

        expect($array)->toBeArray();
        expect(count($array))->toBeGreaterThan(0);
    });

    it('creates collection with user context', function () {
        $user = new User(['id' => 1, 'role' => 'admin']);
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = StateCollectionResource::nextStates($post, $user);
        $array = $resource->toArray($request);

        expect($array)->toBeArray();
    });

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

    it('returns states for model from all states', function () {
        $request = Request::create('/');
        $resource = StateCollectionResource::forModel(Post::class);

        $array = $resource->toArray($request);

        $names = collect($array)->pluck('name')->all();
        expect($names)->toContain('draft');
        expect($names)->toContain('review');
    });
});
