<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Exceptions\InvalidStateException;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionNotAllowedException;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
});

describe('State Transitions - Can Transition', function (): void {
    /**
     * Scenario: Verify that a model can check if a transition to a specific state is allowed using the state class
     * Setup: Create a Post in Draft state (default)
     * Assertion: Draft → Review transition is allowed
     */
    it('can check allowed transition with class', function (): void {
        $post = Post::create(['title' => 'Test']);

        expect($post->canTransitionTo(Review::class))->toBeTrue();
    });

    /**
     * Scenario: Verify that a model can check if a transition is allowed using the state name string
     * Setup: Create a Post in Draft state (default)
     * Assertion: Draft → 'review' transition is allowed
     */
    it('can check allowed transition with name', function (): void {
        $post = Post::create(['title' => 'Test']);

        expect($post->canTransitionTo('review'))->toBeTrue();
    });

    /**
     * Scenario: Verify that disallowed transitions are correctly identified
     * Setup: Create a Post in Draft state (default)
     * Assertion: Draft → Published (skipping Review) is NOT allowed
     */
    it('can check disallowed transition', function (): void {
        $post = Post::create(['title' => 'Test']);

        // Draft cannot transition directly to Published
        expect($post->canTransitionTo(Published::class))->toBeFalse();
    });

    /**
     * Scenario: Verify transition rules change based on current state
     * Setup: Create a Post and manually set it to Review state
     * Assertion: From Review, can go to Published or Rejected, but not back to Draft
     */
    it('can check multi-step transition path', function (): void {
        $post = Post::create(['title' => 'Test']);
        $post->state = 'review';
        $post->save();

        // Review can go to Published or Rejected
        expect($post->canTransitionTo(Published::class))->toBeTrue()
            ->and($post->canTransitionTo(Rejected::class))->toBeTrue()
            ->and($post->canTransitionTo(Draft::class))->toBeFalse();
    });
});

describe('State Transitions - Get Next States', function (): void {
    /**
     * Scenario: Retrieve all states that can be transitioned to from Draft state
     * Setup: Create a Post in Draft state (default)
     * Assertion: Only Review state is reachable from Draft
     */
    it('returns allowed next states from draft', function (): void {
        $post = Post::create(['title' => 'Test']);

        $nextStates = $post->getNextStates();

        expect($nextStates)->toContain(Review::class)
            ->and($nextStates)->toHaveCount(1);
    });

    /**
     * Scenario: Retrieve all states that can be transitioned to from Review state
     * Setup: Create a Post already in Review state
     * Assertion: Both Published and Rejected states are reachable from Review
     */
    it('returns allowed next states from review', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $nextStates = $post->getNextStates();

        expect($nextStates)->toContain(Published::class)
            ->and($nextStates)->toContain(Rejected::class)
            ->and($nextStates)->toHaveCount(2);
    });

    /**
     * Scenario: Verify final states return no next states
     * Setup: Create a Post in Published state (final state)
     * Assertion: No transitions available from Published state
     */
    it('returns empty array for final state', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        $nextStates = $post->getNextStates();

        expect($nextStates)->toBe([]);
    });

    /**
     * Scenario: Handle edge case where model has no state set
     * Setup: Create a new Post without persisting or setting state
     * Assertion: Returns empty array when state is null
     */
    it('returns empty array when model has no state', function (): void {
        $post = new Post;
        // Don't set state

        expect($post->getNextStates())->toBe([]);
    });
});

describe('State Transitions - Execute Transition', function (): void {
    /**
     * Scenario: Execute a state transition using state class string
     * Setup: Create a Post in Draft state
     * Assertion: Transition to Review succeeds, returns success result with metadata
     */
    it('can transition with class string', function (): void {
        $post = Post::create(['title' => 'Test']);

        $result = $post->transitionTo(Review::class);

        expect($result)->toBeInstanceOf(TransitionResult::class)
            ->and($result->success)->toBeTrue()
            ->and($result->fromState)->toBe('draft')
            ->and($result->toState)->toBe('review')
            ->and($post->state)->toBeInstanceOf(Review::class);
    });

    /**
     * Scenario: Execute a state transition using state name string
     * Setup: Create a Post in Draft state
     * Assertion: Transition to 'review' succeeds, model state updates correctly
     */
    it('can transition with state name', function (): void {
        $post = Post::create(['title' => 'Test']);

        $result = $post->transitionTo('review');

        expect($result->success)->toBeTrue()
            ->and($post->getStateName())->toBe('review');
    });

    /**
     * Scenario: Execute a state transition using a state instance object
     * Setup: Create a Post in Review, get state instance, reset to Draft, then transition
     * Assertion: Transition using state instance succeeds
     */
    it('can transition with state instance', function (): void {
        $post = Post::create(['title' => 'Test']);
        $post->state = 'review'; // set to review first
        $post->save();

        // Get a Review state instance from the model
        $reviewState = $post->state;

        // Reset to draft
        $post->state = 'draft';
        $post->save();

        $result = $post->transitionTo($reviewState);

        expect($result->success)->toBeTrue()
            ->and($post->state)->toBeInstanceOf(Review::class);
    });

    /**
     * Scenario: Verify state persistence after transition
     * Setup: Create Post, transition to Review, then reload from database
     * Assertion: Reloaded model has the correct state (Review)
     */
    it('persists state after transition', function (): void {
        $post = Post::create(['title' => 'Test']);
        $post->transitionTo(Review::class);

        $fresh = Post::find($post->id);

        expect($fresh->state)->toBeInstanceOf(Review::class);
    });

    /**
     * Scenario: Transition with a reason parameter
     * Setup: Create Post and transition with reason 'Ready for review'
     * Assertion: TransitionResult metadata contains the provided reason
     */
    it('includes reason in transition result', function (): void {
        $post = Post::create(['title' => 'Test']);

        $result = $post->transitionTo(Review::class, reason: 'Ready for review');

        expect($result->metadata['reason'])->toBe('Ready for review');
    });

    /**
     * Scenario: Transition with custom metadata
     * Setup: Create Post and transition with custom metadata array
     * Assertion: Result includes custom metadata plus auto-added model_class and model_id
     */
    it('includes metadata in transition result', function (): void {
        $post = Post::create(['title' => 'Test']);

        $result = $post->transitionTo(Review::class, metadata: ['reviewed_by' => 'admin']);

        expect($result->metadata['reviewed_by'])->toBe('admin')
            ->and($result->metadata['model_class'])->toBe(Post::class)
            ->and($result->metadata['model_id'])->toBe($post->id);
    });
});

describe('State Transitions - Not Allowed', function (): void {
    /**
     * Scenario: Attempt a disallowed state transition
     * Setup: Create Post in Draft state, try to transition directly to Published (not allowed)
     * Assertion: TransitionNotAllowedException is thrown
     */
    it('throws exception for disallowed transition', function (): void {
        $post = Post::create(['title' => 'Test']);

        // Draft cannot go directly to Published
        $post->transitionTo(Published::class);
    })->throws(TransitionNotAllowedException::class);

    /**
     * Scenario: Attempt transition to an invalid/non-existent state
     * Setup: Create Post and try to transition to 'invalid_state'
     * Assertion: InvalidStateException is thrown
     */
    it('throws exception for invalid state', function (): void {
        $post = Post::create(['title' => 'Test']);

        $post->transitionTo('invalid_state');
    })->throws(InvalidStateException::class);
});

describe('State Transitions - Force Transition', function (): void {
    /**
     * Scenario: Force a normally disallowed transition
     * Setup: Create Post in Draft, force transition to Published (bypassing validation)
     * Assertion: Transition succeeds and model state is Published
     */
    it('can force disallowed transition', function (): void {
        $post = Post::create(['title' => 'Test']);

        // Normally Draft -> Published is not allowed
        $result = $post->forceTransitionTo(Published::class);

        expect($result->success)->toBeTrue()
            ->and($post->state)->toBeInstanceOf(Published::class);
    });

    /**
     * Scenario: Force transition with custom metadata
     * Setup: Create Post and force transition with reason and metadata
     * Assertion: Result includes both reason and custom metadata
     */
    it('force transition includes metadata', function (): void {
        $post = Post::create(['title' => 'Test']);

        $result = $post->forceTransitionTo(
            Published::class,
            reason: 'Emergency publish',
            metadata: ['admin' => true]
        );

        expect($result->metadata['reason'])->toBe('Emergency publish')
            ->and($result->metadata['admin'])->toBeTrue();
    });

    /**
     * Scenario: Force transition to invalid state still fails
     * Setup: Create Post and attempt force transition to non-existent state
     * Assertion: InvalidStateException is thrown even with force
     */
    it('throws for invalid state even when forced', function (): void {
        $post = Post::create(['title' => 'Test']);

        $post->forceTransitionTo('invalid_state');
    })->throws(InvalidStateException::class);
});

describe('State Transitions - Full Workflow', function (): void {
    /**
     * Scenario: Execute complete happy path workflow
     * Setup: Create Post in Draft, transition through Review to Published
     * Assertion: Each transition succeeds and model state updates correctly at each step
     */
    it('can complete full state workflow', function (): void {
        $post = Post::create(['title' => 'Test']);

        // Draft -> Review -> Published
        expect($post->getStateName())->toBe('draft');

        $post->transitionTo(Review::class);
        expect($post->getStateName())->toBe('review');

        $post->transitionTo(Published::class);
        expect($post->getStateName())->toBe('published');
    });

    /**
     * Scenario: Execute rejection and resubmission workflow
     * Setup: Create Post, transition through Review -> Rejected -> back to Draft
     * Assertion: Model ends in Draft state after rejection cycle
     */
    it('can complete rejection workflow', function (): void {
        $post = Post::create(['title' => 'Test']);

        // Draft -> Review -> Rejected -> Draft
        $post->transitionTo(Review::class);
        $post->transitionTo(Rejected::class);
        $post->transitionTo(Draft::class);

        expect($post->getStateName())->toBe('draft');
    });
});
