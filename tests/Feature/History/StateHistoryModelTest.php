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

describe('StateHistory Model', function () {
    it('can create a state history entry', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => null,
        ]);

        expect($history)->toBeInstanceOf(StateHistory::class);
        expect($history->from_state)->toBe('draft');
        expect($history->to_state)->toBe('review');
        expect($history->field)->toBe('state');
    });

    it('can retrieve model through morph relation', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        expect($history->model)->toBeInstanceOf(Post::class);
        expect($history->model->id)->toBe($post->id);
    });

    it('can retrieve performer through morph relation', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'role' => 'editor']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => $user->id,
            'performer_type' => User::class,
        ]);

        expect($history->performer)->toBeInstanceOf(User::class);
        expect($history->performer->id)->toBe($user->id);
    });
});

describe('StateHistory Scopes', function () {
    it('can filter by model using forModel scope', function () {
        $post1 = Post::create(['title' => 'Test 1', 'state' => 'draft']);
        $post2 = Post::create(['title' => 'Test 2', 'state' => 'draft']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post1->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post2->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'published',
        ]);

        $post1History = StateHistory::forModel($post1)->get();
        expect($post1History)->toHaveCount(1);
        expect($post1History->first()->to_state)->toBe('review');
    });

    it('can filter by from and to state', function () {
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

        expect(StateHistory::fromState('draft')->count())->toBe(1);
        expect(StateHistory::toState('published')->count())->toBe(1);
        expect(StateHistory::fromState('review')->toState('published')->count())->toBe(1);
    });

    it('can filter by field', function () {
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
            'field' => 'status',
            'from_state' => 'pending',
            'to_state' => 'active',
        ]);

        expect(StateHistory::forField('state')->count())->toBe(1);
        expect(StateHistory::forField('status')->count())->toBe(1);
    });

    it('can filter by performer', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
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

        expect(StateHistory::byPerformer($user1)->count())->toBe(1);
        expect(StateHistory::byPerformer($user2)->count())->toBe(1);
    });

    it('can filter automated transitions', function () {
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

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'performer_id' => $user->id,
            'performer_type' => User::class,
        ]);

        expect(StateHistory::automated()->count())->toBe(1);
        expect(StateHistory::manual()->count())->toBe(1);
    });

    it('can order by latest and oldest', function () {
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

        $latest = StateHistory::latestFirst()->first();
        $oldest = StateHistory::oldestFirst()->first();

        expect($latest->to_state)->toBe('published');
        expect($oldest->to_state)->toBe('review');
    });

    it('can filter by date range', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'published']);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'created_at' => now()->subDays(5),
        ]);

        StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'created_at' => now(),
        ]);

        expect(StateHistory::lastDays(3)->count())->toBe(1);
        expect(StateHistory::lastDays(10)->count())->toBe(2);
    });
});

describe('StateHistory Helper Methods', function () {
    it('returns summary string', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
        ]);

        $summary = $history->getSummary();

        expect($summary)->toContain('draft');
        expect($summary)->toContain('review');
        expect($summary)->toContain('System');
        expect($summary)->toContain('state');
    });

    it('returns summary with performer name', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => $user->id,
            'performer_type' => User::class,
        ]);

        $summary = $history->getSummary();

        expect($summary)->toContain('John Doe');
    });

    it('checks if automated', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $automated = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => null,
        ]);

        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $manual = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'review',
            'to_state' => 'published',
            'performer_id' => $user->id,
            'performer_type' => User::class,
        ]);

        expect($automated->isAutomated())->toBeTrue();
        expect($automated->isManual())->toBeFalse();
        expect($manual->isAutomated())->toBeFalse();
        expect($manual->isManual())->toBeTrue();
    });

    it('checks wasPerformedBy', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user1 = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $user2 = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'performer_id' => $user1->id,
            'performer_type' => User::class,
        ]);

        expect($history->wasPerformedBy($user1))->toBeTrue();
        expect($history->wasPerformedBy($user2))->toBeFalse();
        expect($history->wasPerformedBy($user1->id))->toBeTrue();
    });

    it('can get and check metadata', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'metadata' => ['note' => 'Test note', 'priority' => 'high'],
        ]);

        expect($history->getMetadataValue('note'))->toBe('Test note');
        expect($history->getMetadataValue('priority'))->toBe('high');
        expect($history->getMetadataValue('missing', 'default'))->toBe('default');
        expect($history->hasMetadata('note'))->toBeTrue();
        expect($history->hasMetadata('missing'))->toBeFalse();
    });

    it('converts to summary array', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $history = StateHistory::create([
            'model_type' => Post::class,
            'model_id' => $post->id,
            'field' => 'state',
            'from_state' => 'draft',
            'to_state' => 'review',
            'reason' => 'Test reason',
        ]);

        $array = $history->toSummaryArray();

        expect($array)->toHaveKeys(['id', 'from', 'to', 'field', 'performer', 'reason', 'date', 'human_date']);
        expect($array['from'])->toBe('draft');
        expect($array['to'])->toBe('review');
        expect($array['reason'])->toBe('Test reason');
    });
});
