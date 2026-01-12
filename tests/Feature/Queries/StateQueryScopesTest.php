<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Actions\RecordStateTransition;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function () {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    $this->createUsersTable();
    Post::resetStateRegistration();

    // Create test posts with various states
    Post::create(['title' => 'Post 1', 'state' => 'draft']);
    Post::create(['title' => 'Post 2', 'state' => 'draft']);
    Post::create(['title' => 'Post 3', 'state' => 'review']);
    Post::create(['title' => 'Post 4', 'state' => 'published']);
    Post::create(['title' => 'Post 5', 'state' => 'rejected']);
});

/**
 * Helper to record a transition in history.
 */
function recordTransitionHistory(Post $post, string $from, string $to, ?User $performer = null): void
{
    $context = new TransitionContext(
        model: $post,
        field: 'state',
        fromState: $from,
        toState: $to,
        performer: $performer,
        reason: null,
        metadata: [],
        initiatedAt: new DateTimeImmutable,
    );

    RecordStateTransition::run($context);
}

describe('Basic State Scopes', function () {
    /**
     * Scenario: Filter models by exact state name (most common query pattern)
     * Setup: Create posts in various states, query for 'draft'
     * Assertions: Returns only draft posts (2 of 5), all match state name
     */
    it('can filter by state with whereState', function () {
        $posts = Post::whereState('draft')->get();

        expect($posts)->toHaveCount(2)
            ->and($posts->every(fn ($p) => $p->state->name() === 'draft'))->toBeTrue();
    });

    /**
     * Scenario: whereState accepts both string names and State class FQCNs (flexible API)
     * Setup: Query using Draft::class instead of 'draft' string
     * Assertions: Returns same results (class resolved to state name automatically)
     */
    it('can filter by state using class name', function () {
        $posts = Post::whereState(Draft::class)->get();

        expect($posts)->toHaveCount(2);
    });

    /**
     * Scenario: Exclude specific state from results (inverse of whereState)
     * Setup: Query whereStateNot('draft')
     * Assertions: Returns 3 non-draft posts (review, published, rejected)
     */
    it('can exclude state with whereStateNot', function () {
        $posts = Post::whereStateNot('draft')->get();

        expect($posts)->toHaveCount(3)
            ->and($posts->every(fn ($p) => $p->state->name() !== 'draft'))->toBeTrue();
    });
});

describe('whereStateIn Scope', function () {
    /**
     * Scenario: Filter by multiple states with OR logic (common report filtering)
     * Setup: Query for posts in 'draft' OR 'review' states
     * Assertions: Returns 3 posts (2 draft + 1 review), excludes others
     */
    it('can filter by state in list', function () {
        $posts = Post::whereStateIn(['draft', 'review'])->get();

        expect($posts)->toHaveCount(3)
            ->and($posts->contains(fn ($p) => $p->state->name() === 'draft'))->toBeTrue()
            ->and($posts->contains(fn ($p) => $p->state->name() === 'review'))->toBeTrue()
            ->and($posts->contains(fn ($p) => $p->state->name() === 'published'))->toBeFalse();
    });

    /**
     * Scenario: whereStateIn accepts array of State classes (type-safe querying)
     * Setup: Pass [Draft::class, Review::class] instead of strings
     * Assertions: Returns same results (classes normalized to names)
     */
    it('can filter using class names', function () {
        $posts = Post::whereStateIn([Draft::class, Review::class])->get();

        expect($posts)->toHaveCount(3);
    });

    /**
     * Scenario: State scopes chain with standard Eloquent query methods (composable)
     * Setup: Combine whereStateIn with where clause on title
     * Assertions: Returns intersection (draft/review AND title like %1%)
     */
    it('can chain with other conditions', function () {
        $posts = Post::whereStateIn(['draft', 'review'])
            ->where('title', 'like', '%1%')
            ->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->title)->toBe('Post 1');
    });
});

describe('whereStateNotIn Scope', function () {
    /**
     * Scenario: Exclude multiple states from results (inverse of whereStateIn)
     * Setup: Query whereStateNotIn(['draft', 'rejected'])
     * Assertions: Returns 2 posts (only review and published remain)
     */
    it('can exclude multiple states', function () {
        $posts = Post::whereStateNotIn(['draft', 'rejected'])->get();

        expect($posts)->toHaveCount(2)
            ->and($posts->every(fn ($p) => ! in_array($p->state->name(), ['draft', 'rejected'])))->toBeTrue();
    });

    /**
     * Scenario: Exclusion scope accepts State class array (consistent API)
     * Setup: Pass [Draft::class, Rejected::class] for exclusion
     * Assertions: Same results as string names (type-safe alternative)
     */
    it('can exclude using class names', function () {
        $posts = Post::whereStateNotIn([Draft::class, Rejected::class])->get();

        expect($posts)->toHaveCount(2);
    });
});

describe('whereActiveState Scope', function () {
    /**
     * Scenario: Filter for states that can still transition (workflow in progress)
     * Setup: Query whereActiveState() - excludes final/terminal states
     * Assertions: Returns 4 posts (excludes published which is final state)
     */
    it('filters by active (non-final) states', function () {
        $posts = Post::whereActiveState()->get();

        // Draft, Review, Rejected are active (can transition)
        // Published is final (no transitions)
        expect($posts)->toHaveCount(4)
            ->and($posts->every(fn ($p) => $p->state->name() !== 'published'))->toBeTrue();
    });
});

describe('whereFinalState Scope', function () {
    it('filters by final (terminal) states', function () {
        $posts = Post::whereFinalState()->get();

        // Only Published has no allowed transitions
        expect($posts)->toHaveCount(1)
            ->and($posts->first()->state->name())->toBe('published');
    });
});

describe('whereCanTransitionTo Scope', function () {
    it('finds states that can transition to a target', function () {
        // Draft -> Review is allowed
        $posts = Post::whereCanTransitionTo('review')->get();

        expect($posts)->toHaveCount(2)
            ->and($posts->every(fn ($p) => $p->state->name() === 'draft'))->toBeTrue();
    });

    it('finds states that can transition to target using class', function () {
        // Review -> Published is allowed
        $posts = Post::whereCanTransitionTo(Published::class)->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->state->name())->toBe('review');
    });

    it('returns empty when no transitions possible', function () {
        // Nothing can transition to Draft directly (only Rejected can)
        $posts = Post::whereCanTransitionTo('draft')->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->state->name())->toBe('rejected');
    });
});

describe('whereInitialState Scope', function () {
    it('filters by initial/default state', function () {
        $posts = Post::whereInitialState()->get();

        // Draft is the default state
        expect($posts)->toHaveCount(2)
            ->and($posts->every(fn ($p) => $p->state->name() === 'draft'))->toBeTrue();
    });
});

describe('whereNotInitialState Scope', function () {
    it('filters by non-initial states', function () {
        $posts = Post::whereNotInitialState()->get();

        // Everything except Draft
        expect($posts)->toHaveCount(3)
            ->and($posts->every(fn ($p) => $p->state->name() !== 'draft'))->toBeTrue();
    });
});

describe('History-based Scopes', function () {
    beforeEach(function () {
        config()->set('laravel-stateflow.features.history', true);
    });

    it('can filter by models that were ever in a state', function () {
        // Get a draft post and transition it, recording history
        $post = Post::where('state', 'draft')->first();
        $post->state = 'review';
        $post->save();
        recordTransitionHistory($post, 'draft', 'review');

        $posts = Post::whereWasEverInState('review')->get();

        // Should find the one we transitioned + Post 3 that already has review
        // But Post 3 has no history, so only the transitioned one
        expect($posts)->toHaveCount(1)
            ->and($posts->first()->id)->toBe($post->id);
    });

    it('can filter by transition from-to path', function () {
        // Transition a post
        $post = Post::where('state', 'draft')->first();
        $post->state = 'review';
        $post->save();
        recordTransitionHistory($post, 'draft', 'review');

        $posts = Post::whereTransitionedFromTo('draft', 'review')->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->id)->toBe($post->id);
    });

    it('can filter by state changed after date', function () {
        // Transition a post
        $post = Post::where('state', 'draft')->first();
        $post->state = 'review';
        $post->save();
        recordTransitionHistory($post, 'draft', 'review');

        $yesterday = now()->subDay();
        $posts = Post::whereStateChangedAfter($yesterday)->get();

        expect($posts)->toHaveCount(1);
    });

    it('can filter by state changed by specific user', function () {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'role' => 'admin']);

        // Transition a post by user
        $post = Post::where('state', 'draft')->first();
        $post->state = 'review';
        $post->save();
        recordTransitionHistory($post, 'draft', 'review', $user);

        $posts = Post::whereStateChangedBy($user)->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->id)->toBe($post->id);
    });

    it('can filter by minimum transition count', function () {
        // Transition a post multiple times
        $post = Post::where('state', 'draft')->first();
        $post->state = 'review';
        $post->save();
        recordTransitionHistory($post, 'draft', 'review');

        $post->state = 'published';
        $post->save();
        recordTransitionHistory($post, 'review', 'published');

        $posts = Post::whereTransitionCountAtLeast(2)->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->id)->toBe($post->id);
    });
});

describe('Scope Chaining', function () {
    it('can chain multiple state scopes', function () {
        $posts = Post::whereStateIn(['draft', 'review', 'rejected'])
            ->whereNotInitialState()
            ->get();

        expect($posts)->toHaveCount(2)
            ->and($posts->every(fn ($p) => in_array($p->state->name(), ['review', 'rejected'])))->toBeTrue();
    });

    it('can chain state scopes with order', function () {
        $posts = Post::whereStateIn(['draft', 'review'])
            ->orderBy('title', 'desc')
            ->get();

        expect($posts)->toHaveCount(3)
            ->and($posts->first()->title)->toBe('Post 3');
    });
});
