<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\PostState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
});

describe('HasStates Trait - State Access', function (): void {
    it('casts state to state object', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->state)->toBeInstanceOf(Draft::class)
            ->and($post->state)->toBeInstanceOf(PostState::class);
    });

    it('gets current state with getState()', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->getState())->toBeInstanceOf(Draft::class);
    });

    it('gets state name as string', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->getStateName())->toBe('draft');
    });

    it('gets state title', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->getStateTitle())->toBe('Draft');
    });

    it('gets state color', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->getStateColor())->toBe('primary');
    });

    it('checks if model is in specific state with class', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->isState(Draft::class))->toBeTrue()
            ->and($post->isState(Review::class))->toBeFalse();
    });

    it('checks if model is in specific state with name', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->isState('draft'))->toBeTrue()
            ->and($post->isState('review'))->toBeFalse();
    });

    it('checks if model is in specific state with instance', function (): void {
        $post = new Post;
        $post->state = 'draft';

        // Get the actual state instance from the model
        $draftState = $post->state;

        expect($post->isState($draftState))->toBeTrue();
    });

    it('checks if model is in any of given states', function (): void {
        $post = new Post;
        $post->state = 'draft';

        expect($post->isAnyState([Draft::class, Review::class]))->toBeTrue()
            ->and($post->isAnyState([Review::class, Published::class]))->toBeFalse();
    });

    it('returns null for state access when not set', function (): void {
        $post = new Post;
        // Don't set state

        expect($post->getState())->toBeNull()
            ->and($post->getStateName())->toBeNull();
    });
});

describe('HasStates Trait - Default State', function (): void {
    it('sets default state when creating model', function (): void {
        $post = Post::create(['title' => 'Test Post']);

        expect($post->state)->toBeInstanceOf(Draft::class)
            ->and($post->getStateName())->toBe('draft');
    });

    it('does not override explicitly set state on create', function (): void {
        $post = Post::create([
            'title' => 'Test Post',
            'state' => 'review',
        ]);

        expect($post->state)->toBeInstanceOf(Review::class)
            ->and($post->getStateName())->toBe('review');
    });

    it('persists state to database', function (): void {
        $post = Post::create(['title' => 'Test Post']);

        $fresh = Post::find($post->id);

        expect($fresh->state)->toBeInstanceOf(Draft::class)
            ->and($fresh->getStateName())->toBe('draft');
    });
});

describe('HasStates Trait - State Configuration', function (): void {
    it('can access state config statically', function (): void {
        $config = Post::getStateConfig('state');

        expect($config)->not->toBeNull()
            ->and($config->getBaseStateClass())->toBe(PostState::class);
    });

    it('can get all state configs', function (): void {
        $configs = Post::getAllStateConfigs();

        expect($configs)->toHaveKey('state')
            ->and($configs['state']->getBaseStateClass())->toBe(PostState::class);
    });

    it('can check if state config exists', function (): void {
        expect(Post::hasStateConfig('state'))->toBeTrue()
            ->and(Post::hasStateConfig('other'))->toBeFalse();
    });

    it('throws when accessing non-existent config', function (): void {
        Post::getStateConfig('non_existent');
    })->throws(\Hpwebdeveloper\LaravelStateflow\Exceptions\StateConfigurationException::class);
});
