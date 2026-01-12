<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Actions\ValidateTransition;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

// ============================================================================
// VALIDATE TRANSITION ACTION TESTS
// ============================================================================

describe('ValidateTransition Action', function (): void {

    it('validates allowed transition', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $result = ValidateTransition::run($data);

        expect($result['valid'])->toBeTrue();
        expect($result['reasons'])->toBeEmpty();
    });

    it('invalidates disallowed transition', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Published::class, // Draft cannot go directly to Published
        );

        $result = ValidateTransition::run($data);

        expect($result['valid'])->toBeFalse();
        expect($result['reasons'])->not->toBeEmpty();
        expect($result['reasons'][0])->toContain('not allowed');
    });

    it('provides detailed reasons for failure', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Published::class,
        );

        $result = ValidateTransition::run($data);

        expect($result['reasons'])->toContain(
            "Transition from 'draft' to 'published' is not allowed."
        );
    });

    it('can be called using make', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action = ValidateTransition::make();
        $result = $action->handle($data);

        expect($result['valid'])->toBeTrue();
    });

});

// ============================================================================
// INTEGRATION WITH MODEL TESTS
// ============================================================================

describe('ValidateTransition Model Integration', function (): void {

    it('validates using model method', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Valid transition
        $validResult = $post->validateTransitionTo(Review::class);
        expect($validResult['valid'])->toBeTrue();

        // Invalid transition
        $invalidResult = $post->validateTransitionTo(Published::class);
        expect($invalidResult['valid'])->toBeFalse();
    });

    it('handles state name strings', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Valid transition with state name
        $result = $post->validateTransitionTo('review');
        expect($result['valid'])->toBeTrue();
    });

    it('returns failure for unknown state', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = $post->validateTransitionTo('nonexistent_state');

        expect($result['valid'])->toBeFalse();
        expect($result['reasons'][0])->toContain('Unknown state');
    });

});

// ============================================================================
// EDGE CASES
// ============================================================================

describe('ValidateTransition Edge Cases', function (): void {

    it('handles transition to same state', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Draft::class,
        );

        $result = ValidateTransition::run($data);

        // Transition to same state is not explicitly allowed
        expect($result['valid'])->toBeFalse();
    });

    it('handles models without canTransitionTo method gracefully', function (): void {
        // Create a mock model without canTransitionTo
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'posts';

            protected $guarded = [];
        };

        $data = new TransitionData(
            model: $model,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $result = ValidateTransition::run($data);

        // Should pass since no canTransitionTo method exists
        expect($result['valid'])->toBeTrue();
    });

});
