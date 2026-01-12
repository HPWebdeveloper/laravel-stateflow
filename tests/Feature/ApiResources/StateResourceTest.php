<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\StateResourceData;
use Hpwebdeveloper\LaravelStateflow\Http\Resources\StateResource;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

// -----------------------------------------------------------------------------
// StateResourceData DTO Tests
// -----------------------------------------------------------------------------

describe('StateResourceData', function () {
    it('creates from state class with basic properties', function () {
        $data = StateResourceData::fromStateClass(Draft::class);

        expect($data->name)->toBe('draft');
        expect($data->title)->toBe('Draft');
        expect($data->color)->toBe('primary');
        expect($data->isDefault)->toBeTrue();
    });

    it('converts to full array', function () {
        $data = StateResourceData::fromStateClass(Draft::class);
        $array = $data->toArray();

        expect($array)->toHaveKeys([
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
        expect($array['name'])->toBe('draft');
        expect($array['title'])->toBe('Draft');
        expect($array['is_default'])->toBeTrue();
    });

    it('converts to minimal array', function () {
        $data = StateResourceData::fromStateClass(Draft::class);
        $array = $data->toMinimal();

        expect($array)->toHaveKeys(['name', 'title']);
        expect($array)->not->toHaveKey('color');
        expect($array)->not->toHaveKey('is_default');
    });

    it('converts to UI array', function () {
        $data = StateResourceData::fromStateClass(Draft::class);
        $array = $data->toUI();

        expect($array)->toHaveKeys(['name', 'title', 'color', 'icon', 'description']);
        expect($array)->not->toHaveKey('is_default');
        expect($array)->not->toHaveKey('is_current');
    });

    it('detects current state with model context', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $draftData = StateResourceData::fromStateClass(Draft::class, $post);
        $reviewData = StateResourceData::fromStateClass(Review::class, $post);

        expect($draftData->isCurrent)->toBeTrue();
        expect($reviewData->isCurrent)->toBeFalse();
    });

    it('detects can transition to with model context', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $draftData = StateResourceData::fromStateClass(Draft::class, $post);
        $reviewData = StateResourceData::fromStateClass(Review::class, $post);

        // Can't transition to current state
        expect($draftData->canTransitionTo)->toBeFalse();
        // Can transition to Review from Draft
        expect($reviewData->canTransitionTo)->toBeTrue();
    });
});

// -----------------------------------------------------------------------------
// StateResource Tests
// -----------------------------------------------------------------------------

describe('StateResource', function () {
    it('transforms state to full array', function () {
        $request = Request::create('/');
        $resource = new StateResource(Draft::class);

        $array = $resource->toArray($request);

        expect($array)->toHaveKeys([
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
        expect($array['name'])->toBe('draft');
        expect($array['title'])->toBe('Draft');
    });

    it('transforms state to minimal array', function () {
        $request = Request::create('/');
        $resource = (new StateResource(Draft::class))->minimal();

        $array = $resource->toArray($request);

        expect($array)->toHaveKeys(['name', 'title']);
        expect($array)->not->toHaveKey('color');
    });

    it('transforms state to UI array', function () {
        $request = Request::create('/');
        $resource = (new StateResource(Draft::class))->ui();

        $array = $resource->toArray($request);

        expect($array)->toHaveKeys(['name', 'title', 'color', 'icon', 'description']);
        expect($array)->not->toHaveKey('is_current');
    });

    it('detects current state with model context', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = (new StateResource(Draft::class))->withModel($post);
        $array = $resource->toArray($request);

        expect($array['is_current'])->toBeTrue();

        $resource2 = (new StateResource(Review::class))->withModel($post);
        $array2 = $resource2->toArray($request);

        expect($array2['is_current'])->toBeFalse();
    });

    it('calculates can_transition_to with model context', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = (new StateResource(Review::class))->withModel($post);
        $array = $resource->toArray($request);

        expect($array['can_transition_to'])->toBeTrue();
    });

    it('works with state instance instead of class', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = (new StateResource($post->state))->withModel($post);
        $array = $resource->toArray($request);

        expect($array['name'])->toBe('draft');
        expect($array['is_current'])->toBeTrue();
    });

    it('accepts user context', function () {
        $user = new User(['id' => 1, 'role' => 'admin']);
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = (new StateResource(Review::class))
            ->withModel($post)
            ->withUser($user);

        $array = $resource->toArray($request);

        expect($array['name'])->toBe('review');
    });

    it('uses request user when no context user provided', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $request = Request::create('/');

        $resource = (new StateResource(Review::class))->withModel($post);
        $array = $resource->toArray($request);

        // Should still work without a user
        expect($array['name'])->toBe('review');
    });

    it('supports full method to reset format', function () {
        $request = Request::create('/');
        $resource = (new StateResource(Draft::class))->minimal()->full();

        $array = $resource->toArray($request);

        expect($array)->toHaveKey('is_default');
        expect($array)->toHaveKey('color');
    });
});
