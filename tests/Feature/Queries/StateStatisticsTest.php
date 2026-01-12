<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Actions\RecordStateTransition;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\Query\StateStatistics;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;

beforeEach(function () {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    $this->createUsersTable();
    Post::resetStateRegistration();

    config()->set('laravel-stateflow.features.history', true);
});

/**
 * Helper to record a transition in history.
 */
function recordHistory(Post $post, string $from, string $to, ?User $performer = null): void
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

describe('StateStatistics - Count By State', function () {
    beforeEach(function () {
        Post::create(['title' => 'Post 1', 'state' => 'draft']);
        Post::create(['title' => 'Post 2', 'state' => 'draft']);
        Post::create(['title' => 'Post 3', 'state' => 'review']);
        Post::create(['title' => 'Post 4', 'state' => 'published']);
    });

    it('can count by state', function () {
        $counts = StateStatistics::countByState(Post::class);

        expect($counts)->toHaveCount(3)
            ->and($counts['draft'])->toBe(2)
            ->and($counts['review'])->toBe(1)
            ->and($counts['published'])->toBe(1);
    });

    it('returns empty collection for empty table', function () {
        Post::truncate();

        $counts = StateStatistics::countByState(Post::class);

        expect($counts)->toBeEmpty();
    });
});

describe('StateStatistics - Percentage By State', function () {
    beforeEach(function () {
        Post::create(['title' => 'Post 1', 'state' => 'draft']);
        Post::create(['title' => 'Post 2', 'state' => 'draft']);
        Post::create(['title' => 'Post 3', 'state' => 'review']);
        Post::create(['title' => 'Post 4', 'state' => 'published']);
    });

    it('can get percentage by state', function () {
        $percentages = StateStatistics::percentageByState(Post::class);

        expect($percentages)->toHaveCount(3)
            ->and($percentages['draft'])->toBe(50.0)
            ->and($percentages['review'])->toBe(25.0)
            ->and($percentages['published'])->toBe(25.0);
    });

    it('returns empty collection for empty table', function () {
        Post::truncate();

        $percentages = StateStatistics::percentageByState(Post::class);

        expect($percentages)->toBeEmpty();
    });
});

describe('StateStatistics - Transition Patterns', function () {
    beforeEach(function () {
        // Create posts and record transitions in history
        $post1 = Post::create(['title' => 'Post 1', 'state' => 'review']);
        recordHistory($post1, 'draft', 'review');

        $post2 = Post::create(['title' => 'Post 2', 'state' => 'review']);
        recordHistory($post2, 'draft', 'review');

        $post3 = Post::create(['title' => 'Post 3', 'state' => 'published']);
        recordHistory($post3, 'draft', 'review');
        recordHistory($post3, 'review', 'published');
    });

    it('can get most common transitions', function () {
        $transitions = StateStatistics::mostCommonTransitions(Post::class);

        expect($transitions)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($transitions->first()['from'])->toBe('draft')
            ->and($transitions->first()['to'])->toBe('review')
            ->and($transitions->first()['count'])->toBe(3);
    });

    it('respects limit parameter', function () {
        $transitions = StateStatistics::mostCommonTransitions(Post::class, limit: 1);

        expect($transitions)->toHaveCount(1);
    });

    it('returns empty for no transitions', function () {
        // Clear history
        \Illuminate\Support\Facades\DB::table('state_histories')->truncate();

        $transitions = StateStatistics::mostCommonTransitions(Post::class);

        expect($transitions)->toBeEmpty();
    });
});

describe('StateStatistics - Model Transition Count', function () {
    it('can count transitions for a model', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'published']);
        recordHistory($post, 'draft', 'review');
        recordHistory($post, 'review', 'published');

        $count = StateStatistics::transitionCountForModel($post);

        expect($count)->toBe(2);
    });

    it('returns zero for no transitions', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $count = StateStatistics::transitionCountForModel($post);

        expect($count)->toBe(0);
    });
});

describe('StateStatistics - Time Since Last Transition', function () {
    it('returns time since last transition', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'review']);
        recordHistory($post, 'draft', 'review');

        $timeSince = StateStatistics::timeSinceLastTransition($post);

        expect($timeSince)->toBeInt()
            ->and($timeSince)->toBeGreaterThanOrEqual(0)
            ->and($timeSince)->toBeLessThan(5); // Should be very recent
    });

    it('returns null for no transitions', function () {
        $post = Post::create(['title' => 'Test Post', 'state' => 'draft']);

        $timeSince = StateStatistics::timeSinceLastTransition($post);

        expect($timeSince)->toBeNull();
    });
});

describe('StateStatistics - Models With Most Transitions', function () {
    beforeEach(function () {
        // Create posts with varying transition counts (recorded in history)
        $post1 = Post::create(['title' => 'Post 1', 'state' => 'published']);
        recordHistory($post1, 'draft', 'review');
        recordHistory($post1, 'review', 'published');

        $post2 = Post::create(['title' => 'Post 2', 'state' => 'review']);
        recordHistory($post2, 'draft', 'review');

        // Post 3 has no transitions
        Post::create(['title' => 'Post 3', 'state' => 'draft']);
    });

    it('returns models ordered by transition count', function () {
        $models = StateStatistics::modelsWithMostTransitions(Post::class);

        expect($models)->toHaveCount(2)
            ->and($models->first()['count'])->toBe(2)
            ->and($models->last()['count'])->toBe(1);
    });

    it('respects limit parameter', function () {
        $models = StateStatistics::modelsWithMostTransitions(Post::class, limit: 1);

        expect($models)->toHaveCount(1);
    });
});

describe('StateStatistics - Stuck In State', function () {
    it('finds models stuck in a state', function () {
        // Create a post and set its history entry to old date
        $post = Post::create(['title' => 'Stuck Post', 'state' => 'review']);
        recordHistory($post, 'draft', 'review');

        // Manually update the history entry to be old
        \Illuminate\Support\Facades\DB::table('state_histories')
            ->where('model_id', $post->id)
            ->update(['created_at' => now()->subHours(48)]);

        $stuckModels = StateStatistics::stuckInState(Post::class, 'review', 24);

        expect($stuckModels)->toHaveCount(1)
            ->and($stuckModels->first()->id)->toBe($post->id);
    });

    it('excludes recently transitioned models', function () {
        $post = Post::create(['title' => 'Recent Post', 'state' => 'review']);
        recordHistory($post, 'draft', 'review');

        $stuckModels = StateStatistics::stuckInState(Post::class, 'review', 24);

        expect($stuckModels)->toBeEmpty();
    });
});

describe('StateStatistics - Transition Count Over Time', function () {
    beforeEach(function () {
        $post = Post::create(['title' => 'Post 1', 'state' => 'published']);
        recordHistory($post, 'draft', 'review');
        recordHistory($post, 'review', 'published');
    });

    it('groups transitions by day', function () {
        $counts = StateStatistics::transitionCountOverTime(Post::class, groupBy: 'day');

        expect($counts)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($counts->count())->toBeGreaterThanOrEqual(1)
            ->and($counts->values()->first())->toBe(2);
    });
});
