<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Http\Resources\TransitionResource;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

describe('TransitionResource', function () {
    it('formats successful transition result', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = TransitionResult::success(
            model: $post,
            fromState: 'draft',
            toState: 'review'
        );

        $request = Request::create('/');
        $resource = new TransitionResource($result);
        $array = $resource->toArray($request);

        expect($array['success'])->toBeTrue();
        expect($array['from_state'])->toBe('draft');
        expect($array['to_state'])->toBe('review');
        expect($array['model']['id'])->toBe($post->id);
        expect($array['model']['type'])->toBe('Post');
    });

    it('formats failed transition result', function () {
        $result = TransitionResult::failure('Transition not allowed');

        $request = Request::create('/');
        $resource = new TransitionResource($result);
        $array = $resource->toArray($request);

        expect($array['success'])->toBeFalse();
        expect($array['error'])->toBe('Transition not allowed');
    });

    it('excludes error on success', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = TransitionResult::success(
            model: $post,
            fromState: 'draft',
            toState: 'review'
        );

        $resource = new TransitionResource($result);

        // Test through JSON response to see actual serialized output
        $json = $resource->response()->content();
        $data = json_decode($json, true)['data'];

        expect($data)->not->toHaveKey('error');
    });

    it('excludes model on failure', function () {
        $result = TransitionResult::failure('Transition not allowed');

        $resource = new TransitionResource($result);

        // Test through JSON response to see actual serialized output
        $json = $resource->response()->content();
        $data = json_decode($json, true)['data'];

        expect($data)->not->toHaveKey('model');
    });

    it('includes metadata when present', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = TransitionResult::success(
            model: $post,
            fromState: 'draft',
            toState: 'review',
            metadata: ['note' => 'Test transition']
        );

        $resource = new TransitionResource($result);

        // Test through JSON response to see actual serialized output
        $json = $resource->response()->content();
        $data = json_decode($json, true)['data'];

        expect($data['metadata'])->toBe(['note' => 'Test transition']);
    });

    it('excludes metadata when empty', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = TransitionResult::success(
            model: $post,
            fromState: 'draft',
            toState: 'review',
            metadata: []
        );

        $resource = new TransitionResource($result);

        // Test through JSON response to see actual serialized output
        $json = $resource->response()->content();
        $data = json_decode($json, true)['data'];

        expect($data)->not->toHaveKey('metadata');
    });
});
