<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;

beforeEach(function () {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    $this->createUsersTable();
    Post::resetStateRegistration();
});

describe('HasStateHistory Trait', function () {
    /**
     * Scenario: Models using HasStateHistory trait establish polymorphic relation to StateHistory
     * Setup: Create post with HasStateHistory trait included
     * Assertions: stateHistory() returns MorphMany relationship for querying transition records
     */
    it('can get state history relationship', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->stateHistory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
    });

    /**
     * Scenario: Query complete history for a specific state field, ordered chronologically
     * Setup: Create published post with two manual history records (draft->review, review->published)
     * Assertions: Returns 2 records ordered latest first (published transition appears first)
     */
    it('can get state history for field', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => now()->subHour(),
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'created_at' => now(),
        ]);

        $history = $post->getStateHistoryFor('state');

        expect($history)->toHaveCount(2);
        // Should be ordered by latest first
        expect($history->first()->to_state)->toBe('published');
    });

    /**
     * Scenario: Retrieve most recent state transition for "what changed last" queries
     * Setup: Create post with two transitions, second one is review -> published (most recent)
     * Assertions: getLastTransition() returns the published transition, not the earlier review one
     */
    it('can get last transition', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => now()->subHour(),
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'created_at' => now(),
        ]);

        $lastTransition = $post->getLastTransition('state');

        expect($lastTransition)->not->toBeNull();
        expect($lastTransition->to_state)->toBe('published');
    });

    /**
     * Scenario: Retrieve the very first state transition to understand workflow initiation
     * Setup: Create post with two transitions, first one is draft -> review (earliest)
     * Assertions: getFirstTransition() returns the review transition, ignoring later ones
     */
    it('can get first transition', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => now()->subHour(),
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'created_at' => now(),
        ]);

        $firstTransition = $post->getFirstTransition('state');

        expect($firstTransition)->not->toBeNull();
        expect($firstTransition->to_state)->toBe('review');
    });

    /**
     * Scenario: Retrieve only the N most recent transitions to avoid loading entire history
     * Setup: Create 10 sequential state transitions, request only the 5 most recent
     * Assertions: Returns exactly 5 records (pagination works), most recent transitions returned
     */
    it('can get recent transitions', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        for ($i = 0; $i < 10; $i++) {
            StateHistory::create([
                'model_type' => Post::class,
                'model_id' => $post->id,
                'field' => 'state',
                'from_state' => 'state_'.$i,
                'to_state' => 'state_'.($i + 1),
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $recent = $post->getRecentTransitions(5, 'state');

        expect($recent)->toHaveCount(5);
    });

    /**
     * Scenario: Filter transition history by specific user for accountability and auditing
     * Setup: Create two users, John transitions to review, Jane transitions to published
     * Assertions: getTransitionsByPerformer(John) returns only John's transition (review)
     */
    it('can get transitions by performer', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);
        $user1 = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => $user1->id,
            'performer_type' => User::class,
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'performer_id' => $user2->id,
            'performer_type' => User::class,
        ]);

        $user1Transitions = $post->getTransitionsByPerformer($user1, 'state');

        expect($user1Transitions)->toHaveCount(1);
        expect($user1Transitions->first()->to_state)->toBe('review');
    });

    /**
     * Scenario: Query when and how model entered a particular state for compliance/reporting
     * Setup: Create transitions to review and published states
     * Assertions: getTransitionsToState('review') finds the draft->review transition
     */
    it('can get transitions to a specific state', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
        ]);

        $toReview = $post->getTransitionsToState('review', 'state');
        $toPublished = $post->getTransitionsToState('published', 'state');

        expect($toReview)->toHaveCount(1);
        expect($toPublished)->toHaveCount(1);
    });

    /**
     * Scenario: Query transitions that originated from a particular state to analyze workflow paths
     * Setup: Create draft->review and review->published transitions
     * Assertions: getTransitionsFromState('draft') finds only the draft->review transition
     */
    it('can get transitions from a specific state', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
        ]);

        $fromDraft = $post->getTransitionsFromState('draft', 'state');
        $fromReview = $post->getTransitionsFromState('review', 'state');

        expect($fromDraft)->toHaveCount(1);
        expect($fromReview)->toHaveCount(1);
    });

    /**
     * Scenario: Get total count of all state transitions for metrics and analytics
     * Setup: Create two state transitions (draft->review, review->published)
     * Assertions: countTransitions() returns 2, both with and without explicit field parameter
     */
    it('can count transitions', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
        ]);

        expect($post->countTransitions('state'))->toBe(2);
        expect($post->countTransitions())->toBe(2);
    });

    /**
     * Scenario: Check if model has ever been in a state during its entire lifecycle
     * Setup: Create history showing transitions through draft and review (currently published)
     * Assertions: wasEverInState() returns true for states in history (draft, review), false for rejected
     */
    it('can check if model was ever in state', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        expect($post->wasEverInState('draft', 'state'))->toBeTrue();
        expect($post->wasEverInState('review', 'state'))->toBeTrue();
        expect($post->wasEverInState('rejected', 'state'))->toBeFalse();
    });

    it('can check if model transitioned from one state to another', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        expect($post->hasTransitionedFromTo('draft', 'review', 'state'))->toBeTrue();
        expect($post->hasTransitionedFromTo('draft', 'published', 'state'))->toBeFalse();
    });

    it('can get state timeline', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        $timeline = $post->getStateTimeline('state');

        expect($timeline)->toBeArray();
        expect($timeline)->toHaveCount(1);
        expect($timeline[0])->toHaveKeys(['id', 'from', 'to', 'performer', 'date', 'human_date']);
        expect($timeline[0]['from'])->toBe('draft');
        expect($timeline[0]['to'])->toBe('review');
    });

    it('can get unique states', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
        ]);

        $uniqueStates = $post->getUniqueStates('state');

        expect($uniqueStates)->toContain('draft');
        expect($uniqueStates)->toContain('review');
        expect($uniqueStates)->toContain('published');
    });

    it('can get transition counts by state', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'draft',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        $counts = $post->getTransitionCountsByState('state');

        expect($counts['review'])->toBe(2);
        expect($counts['draft'])->toBe(1);
    });

    it('can check for automated transitions', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => null,
        ]);

        expect($post->hasAutomatedTransitions('state'))->toBeTrue();

        $post2 = Post::create(['title' => 'Test 2', 'state' => 'review']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post2->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => $user->id,
            'performer_type' => User::class,
        ]);

        expect($post2->hasAutomatedTransitions('state'))->toBeFalse();
    });

    it('can clear state history', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
        ]);

        expect($post->countTransitions('state'))->toBe(2);

        $deleted = $post->clearStateHistory('state');

        expect($deleted)->toBe(2);
        expect($post->countTransitions('state'))->toBe(0);
    });

    it('returns null for time in current state when no history', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Without created_at, should return null
        $post->created_at = null;
        $time = $post->getTimeInCurrentState('state');

        expect($time)->toBeNull();
    });

    it('returns time in current state from last transition', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => now()->subHours(2),
        ]);

        $time = $post->getTimeInCurrentState('state');

        expect($time)->toContain('2 hours');
    });

    it('returns current state entered at timestamp', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $enteredAt = now()->subHours(3);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => $enteredAt,
        ]);

        $timestamp = $post->getCurrentStateEnteredAt('state');

        expect($timestamp)->not->toBeNull();
        expect($timestamp->format('Y-m-d H:i'))->toBe($enteredAt->format('Y-m-d H:i'));
    });
});

describe('HasStateHistory Duration Methods', function () {
    it('can get duration between states', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => now()->subHours(2),
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'created_at' => now(),
        ]);

        $duration = $post->getDurationBetweenStates('review', 'published', 'state');

        // Should be approximately 2 hours (7200 seconds)
        expect($duration)->toBeGreaterThan(7000);
        expect($duration)->toBeLessThan(7400);
    });

    it('returns null for duration when states not found', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $duration = $post->getDurationBetweenStates('review', 'published', 'state');

        expect($duration)->toBeNull();
    });

    it('can get duration from state to now', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => now()->subHours(1),
        ]);

        $duration = $post->getDurationFromState('review', 'state');

        // Should be approximately 1 hour (3600 seconds)
        expect($duration)->toBeGreaterThan(3500);
        expect($duration)->toBeLessThan(3700);
    });
});
