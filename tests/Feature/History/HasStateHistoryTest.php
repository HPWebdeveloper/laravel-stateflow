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
    it('can get state history relationship', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        expect($post->stateHistory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
    });

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
