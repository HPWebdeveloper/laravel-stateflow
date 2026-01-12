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

    /**
     * Scenario: Validation succeeds for transitions defined in state machine (pre-flight check)
     * Setup: Attempt draft->review transition (defined as valid path)
     * Assertions: Returns valid=true with empty reasons array (no blocking issues)
     */
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

    /**
     * Scenario: Validation rejects transitions not defined in state graph (enforces workflow)
     * Setup: Attempt draft->published (skips required review step)
     * Assertions: Returns valid=false with reasons explaining rejection
     */
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

    /**
     * Scenario: Validation provides human-readable error messages for UI display
     * Setup: Attempt invalid draft->published transition
     * Assertions: Reasons array contains specific 'from X to Y not allowed' message
     */
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

    /**
     * Scenario: Action supports make() factory pattern for dependency injection
     * Setup: Instantiate via make(), call handle() instead of static run()
     * Assertions: Same validation result (factory pattern for testability)
     */
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

    /**
     * Scenario: Model provides validateTransitionTo() convenience method wrapping action
     * Setup: Call model method for both valid and invalid transitions
     * Assertions: Returns validation result dictionary (same as calling action directly)
     */
    it('validates using model method', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Valid transition
        $validResult = $post->validateTransitionTo(Review::class);
        expect($validResult['valid'])->toBeTrue();

        // Invalid transition
        $invalidResult = $post->validateTransitionTo(Published::class);
        expect($invalidResult['valid'])->toBeFalse();
    });

    /**
     * Scenario: Validation accepts normalized state name strings (not just class references)
     * Setup: Pass 'review' string instead of Review::class
     * Assertions: Validation works with string names (flexible input format)
     */
    it('handles state name strings', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Valid transition with state name
        $result = $post->validateTransitionTo('review');
        expect($result['valid'])->toBeTrue();
    });

    /**
     * Scenario: Validation detects and rejects transitions to non-existent states
     * Setup: Attempt transition to 'nonexistent_state' (not in state registry)
     * Assertions: Returns invalid result (state must exist before checking paths)
     */
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
