<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Contracts\TransitionContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Hpwebdeveloper\LaravelStateflow\Transition;

beforeEach(function (): void {
    $this->createPostsTable();
});

describe('Transition Class', function (): void {
    it('implements TransitionContract', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);

        expect($transition)->toBeInstanceOf(TransitionContract::class);
    });

    it('can check if transition can execute', function (): void {
        $post = Post::create(['title' => 'Test']);

        $allowedTransition = new Transition($post, 'state', Review::class);
        $disallowedTransition = new Transition($post, 'state', Draft::class);

        expect($allowedTransition->canExecute())->toBeTrue()
            ->and($disallowedTransition->canExecute())->toBeFalse();
    });

    it('executes transition successfully', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);

        $result = $transition->execute();

        expect($result)->toBeInstanceOf(TransitionResult::class)
            ->and($result->success)->toBeTrue()
            ->and($result->fromState)->toBe('draft')
            ->and($result->toState)->toBe('review');
    });

    it('saves model after transition', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);

        $transition->execute();
        $fresh = Post::find($post->id);

        expect($fresh->getStateName())->toBe('review');
    });

    it('can set reason', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);
        $transition->setReason('Ready for review');

        $result = $transition->execute();

        expect($result->metadata['reason'])->toBe('Ready for review');
    });

    it('can set metadata', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);
        $transition->setMetadata(['user_id' => 123]);

        $result = $transition->execute();

        expect($result->metadata['user_id'])->toBe(123);
    });

    it('includes model info in metadata', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);

        $result = $transition->execute();

        expect($result->metadata['model_class'])->toBe(Post::class)
            ->and($result->metadata['model_id'])->toBe($post->id);
    });

    it('can get model', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);

        expect($transition->getModel())->toBe($post);
    });

    it('can get field', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);

        expect($transition->getField())->toBe('state');
    });

    it('can get target state class', function (): void {
        $post = Post::create(['title' => 'Test']);
        $transition = new Transition($post, 'state', Review::class);

        expect($transition->getTargetStateClass())->toBe(Review::class);
    });
});
