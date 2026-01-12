<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Rejected;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
});

describe('Query Scopes - whereState', function (): void {
    it('filters by state using class', function (): void {
        Post::create(['title' => 'Draft 1', 'state' => 'draft']);
        Post::create(['title' => 'Draft 2', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);

        $drafts = Post::whereState(Draft::class)->get();

        expect($drafts)->toHaveCount(2)
            ->and($drafts->pluck('title')->toArray())->toContain('Draft 1', 'Draft 2');
    });

    it('filters by state using name', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);

        $reviews = Post::whereState('review')->get();

        expect($reviews)->toHaveCount(1)
            ->and($reviews->first()->title)->toBe('Review');
    });

    it('filters by state using instance', function (): void {
        $draftPost = Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);

        // Get the state instance from the model
        $draftState = $draftPost->state;
        $drafts = Post::whereState($draftState)->get();

        expect($drafts)->toHaveCount(1);
    });
});

describe('Query Scopes - whereStateIn', function (): void {
    it('filters by multiple states', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);
        Post::create(['title' => 'Published', 'state' => 'published']);

        $notPublished = Post::whereStateIn([Draft::class, Review::class])->get();

        expect($notPublished)->toHaveCount(2)
            ->and($notPublished->pluck('state')->map->name()->toArray())
            ->toContain('draft', 'review');
    });

    it('can mix class and name in whereStateIn', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);
        Post::create(['title' => 'Published', 'state' => 'published']);

        $posts = Post::whereStateIn([Draft::class, 'review'])->get();

        expect($posts)->toHaveCount(2);
    });
});

describe('Query Scopes - whereStateNot', function (): void {
    it('excludes single state', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);
        Post::create(['title' => 'Published', 'state' => 'published']);

        $notDraft = Post::whereStateNot(Draft::class)->get();

        expect($notDraft)->toHaveCount(2)
            ->and($notDraft->pluck('state')->map->name()->toArray())
            ->not->toContain('draft');
    });
});

describe('Query Scopes - whereStateNotIn', function (): void {
    it('excludes multiple states', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);
        Post::create(['title' => 'Published', 'state' => 'published']);
        Post::create(['title' => 'Rejected', 'state' => 'rejected']);

        $active = Post::whereStateNotIn([Draft::class, Rejected::class])->get();

        expect($active)->toHaveCount(2)
            ->and($active->pluck('state')->map->name()->toArray())
            ->toContain('review', 'published');
    });
});

describe('Query Scopes - Field Specification', function (): void {
    it('can specify field in whereState', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);

        $posts = Post::whereState(Draft::class, 'state')->get();

        expect($posts)->toHaveCount(1);
    });

    it('can specify field in whereStateIn', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);

        $posts = Post::whereStateIn([Draft::class, Review::class], 'state')->get();

        expect($posts)->toHaveCount(2);
    });
});

describe('Query Scopes - Chaining', function (): void {
    it('can chain with other where clauses', function (): void {
        Post::create(['title' => 'Draft A', 'state' => 'draft']);
        Post::create(['title' => 'Draft B', 'state' => 'draft']);
        Post::create(['title' => 'Review A', 'state' => 'review']);

        $posts = Post::whereState(Draft::class)
            ->where('title', 'like', '%A')
            ->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->title)->toBe('Draft A');
    });

    it('can chain multiple state scopes', function (): void {
        Post::create(['title' => 'Review', 'state' => 'review']);
        Post::create(['title' => 'Published', 'state' => 'published']);

        // Get review that is not rejected
        $posts = Post::whereStateIn([Review::class, Published::class])
            ->whereStateNot(Published::class)
            ->get();

        expect($posts)->toHaveCount(1)
            ->and($posts->first()->getStateName())->toBe('review');
    });
});

describe('Query Scopes - Empty Results', function (): void {
    it('returns empty when no matching state', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);

        $posts = Post::whereState(Published::class)->get();

        expect($posts)->toBeEmpty();
    });

    it('returns all when no exclusion matches', function (): void {
        Post::create(['title' => 'Draft', 'state' => 'draft']);
        Post::create(['title' => 'Review', 'state' => 'review']);

        $posts = Post::whereStateNot(Published::class)->get();

        expect($posts)->toHaveCount(2);
    });
});
