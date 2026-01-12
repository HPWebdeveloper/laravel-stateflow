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
    Post::resetStateRegistration();

    config()->set('laravel-stateflow.features.history', true);
});

/**
 * Helper to record a transition in history for macros.
 */
function recordMacroHistory(Post $post, string $from, string $to, ?User $performer = null): void
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

describe('orderByState Macro', function () {
    beforeEach(function () {
        Post::create(['title' => 'Post 1', 'state' => 'draft']);
        Post::create(['title' => 'Post 2', 'state' => 'published']);
        Post::create(['title' => 'Post 3', 'state' => 'review']);
        Post::create(['title' => 'Post 4', 'state' => 'rejected']);
    });

    /**
     * Scenario: Sort results by custom state priority order (business-defined workflow order)
     * Setup: Define priority [published, review, draft, rejected], order query by it
     * Assertions: Results sorted in specified order (not alphabetical or default)
     */
    it('can order by custom state priority using string names', function () {
        $posts = Post::orderByState(['published', 'review', 'draft', 'rejected'])->get();

        expect($posts[0]->state->name())->toBe('published')
            ->and($posts[1]->state->name())->toBe('review')
            ->and($posts[2]->state->name())->toBe('draft')
            ->and($posts[3]->state->name())->toBe('rejected');
    });

    /**
     * Scenario: Priority ordering accepts State class array (type-safe alternative)
     * Setup: Pass [Published::class, Review::class, ...] for priority order
     * Assertions: Same ordered results (classes resolved to state names)
     */
    it('can order by custom state priority using class names', function () {
        $posts = Post::orderByState([Published::class, Review::class, Draft::class, Rejected::class])->get();

        expect($posts[0]->state->name())->toBe('published')
            ->and($posts[1]->state->name())->toBe('review')
            ->and($posts[2]->state->name())->toBe('draft')
            ->and($posts[3]->state->name())->toBe('rejected');
    });

    /**
     * Scenario: States not in priority list appear at end (graceful partial ordering)
     * Setup: Only specify [published, draft] in priority (omit review, rejected)
     * Assertions: published first, draft second, unlisted states after in any order
     */
    it('places unlisted states at the end of order', function () {
        // Create posts with only some states listed in order
        $posts = Post::orderByState(['published', 'draft'])->get();

        // published should be first, draft second
        // review and rejected are not in the order list, so they come after
        $stateNames = $posts->pluck('state')->map(fn ($s) => $s->name())->toArray();

        expect($stateNames[0])->toBe('published')
            ->and($stateNames[1])->toBe('draft');
        // review and rejected should be at the end (either order)
        expect(in_array('review', array_slice($stateNames, 2)))->toBeTrue()
            ->and(in_array('rejected', array_slice($stateNames, 2)))->toBeTrue();
    });

    /**
     * Scenario: orderByState chains with standard query methods (composable macro)
     * Setup: Combine orderByState with where clause and limit
     * Assertions: Filtering and sorting work together (macro enhances Eloquent)
     */
    it('can chain with other query methods', function () {
        $posts = Post::orderByState(['published', 'review'])
            ->where('title', 'like', '%Post%')
            ->limit(2)
            ->get();

        expect($posts)->toHaveCount(2)
            ->and($posts[0]->state->name())->toBe('published');
    });
});

describe('whereStatePriorityHigherThan Macro', function () {
    beforeEach(function () {
        Post::create(['title' => 'Post 1', 'state' => 'draft']);
        Post::create(['title' => 'Post 2', 'state' => 'review']);
        Post::create(['title' => 'Post 3', 'state' => 'published']);
    });

    /**
     * Scenario: Filter for states with higher priority than given state (workflow phase filtering)
     * Setup: Query states higher priority than 'published' using [draft, review, published] order
     * Assertions: Returns draft and review (earlier in priority list than published)
     */
    it('filters states with higher priority', function () {
        $priorityOrder = ['draft', 'review', 'published'];

        $posts = Post::whereStatePriorityHigherThan('published', $priorityOrder)->get();

        // draft and review are higher priority (lower index) than published
        expect($posts)->toHaveCount(2)
            ->and($posts->contains(fn ($p) => $p->state->name() === 'draft'))->toBeTrue()
            ->and($posts->contains(fn ($p) => $p->state->name() === 'review'))->toBeTrue();
    });

    /**
     * Scenario: Priority comparison accepts State class (consistent type-safe API)
     * Setup: Pass Published::class and class array for comparison
     * Assertions: Same results (classes resolved, comparison works)
     */
    it('filters using class names', function () {
        $priorityOrder = [Draft::class, Review::class, Published::class];

        $posts = Post::whereStatePriorityHigherThan(Published::class, $priorityOrder)->get();

        expect($posts)->toHaveCount(2);
    });

    /**
     * Scenario: No states higher than highest priority (empty result edge case)
     * Setup: Query for states higher than 'draft' (first in priority list)
     * Assertions: Returns empty (nothing higher priority than first position)
     */
    it('returns empty when target is highest priority', function () {
        $priorityOrder = ['draft', 'review', 'published'];

        $posts = Post::whereStatePriorityHigherThan('draft', $priorityOrder)->get();

        // Nothing is higher priority than draft (index 0)
        expect($posts)->toBeEmpty();
    });

    it('returns empty when state not in priority order', function () {
        $priorityOrder = ['review', 'published'];

        $posts = Post::whereStatePriorityHigherThan('draft', $priorityOrder)->get();

        // draft is not in the priority order
        expect($posts)->toBeEmpty();
    });
});

describe('withTransitionCount Macro', function () {
    beforeEach(function () {
        // Create posts and record history manually
        $post1 = Post::create(['title' => 'Post 1', 'state' => 'published']);
        recordMacroHistory($post1, 'draft', 'review');
        recordMacroHistory($post1, 'review', 'published');

        $post2 = Post::create(['title' => 'Post 2', 'state' => 'review']);
        recordMacroHistory($post2, 'draft', 'review');

        Post::create(['title' => 'Post 3', 'state' => 'draft']);
    });

    it('includes transition count in results', function () {
        $posts = Post::withTransitionCount()->orderBy('id')->get();

        expect($posts[0]->transition_count)->toBe(2)
            ->and($posts[1]->transition_count)->toBe(1)
            ->and($posts[2]->transition_count)->toBe(0);
    });

    it('can order by transition count', function () {
        $posts = Post::withTransitionCount()
            ->orderByDesc('transition_count')
            ->get();

        expect($posts[0]->transition_count)->toBe(2)
            ->and($posts[1]->transition_count)->toBe(1)
            ->and($posts[2]->transition_count)->toBe(0);
    });

    it('can filter models with transitions', function () {
        // Filter models that have history entries using the history table directly
        $historyTable = config('laravel-stateflow.history.table', 'state_histories');

        $posts = Post::whereExists(function ($query) use ($historyTable) {
            $query->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from($historyTable)
                ->whereColumn("{$historyTable}.model_id", 'posts.id')
                ->where("{$historyTable}.model_type", Post::class);
        })->get();

        expect($posts)->toHaveCount(2);
    });
});

describe('withLastTransitionDate Macro', function () {
    beforeEach(function () {
        $post1 = Post::create(['title' => 'Post 1', 'state' => 'review']);
        recordMacroHistory($post1, 'draft', 'review');

        Post::create(['title' => 'Post 2', 'state' => 'draft']);
    });

    it('includes last transition date in results', function () {
        $posts = Post::withLastTransitionDate()->orderBy('id')->get();

        expect($posts[0]->last_transition_at)->not()->toBeNull()
            ->and($posts[1]->last_transition_at)->toBeNull();
    });

    it('can order by last transition date', function () {
        $posts = Post::withLastTransitionDate()
            ->orderByDesc('last_transition_at')
            ->get();

        expect($posts->first()->title)->toBe('Post 1');
    });
});

describe('Macro Chaining', function () {
    beforeEach(function () {
        $post1 = Post::create(['title' => 'Post A', 'state' => 'published']);
        recordMacroHistory($post1, 'draft', 'review');
        recordMacroHistory($post1, 'review', 'published');

        $post2 = Post::create(['title' => 'Post B', 'state' => 'review']);
        recordMacroHistory($post2, 'draft', 'review');

        Post::create(['title' => 'Post C', 'state' => 'review']);
    });

    it('can chain multiple macros with scopes', function () {
        $posts = Post::whereStateIn(['published', 'review'])
            ->withTransitionCount()
            ->orderByState([Published::class, Review::class])
            ->get();

        expect($posts)->toHaveCount(3)
            ->and($posts->first()->state->name())->toBe('published')
            ->and($posts->first()->transition_count)->toBe(2);
    });
});
