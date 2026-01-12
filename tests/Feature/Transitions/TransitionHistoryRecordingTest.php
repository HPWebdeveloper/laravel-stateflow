<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;
use Hpwebdeveloper\LaravelStateflow\Transition;

beforeEach(function (): void {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    $this->createUsersTable();
    Post::resetStateRegistration();
});

// ============================================================================
// TRANSITION CLASS HISTORY RECORDING TESTS
// ============================================================================

describe('Transition Class Records History', function (): void {

    it('records history when transition executes successfully', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->execute();

        expect(StateHistory::count())->toBe(1);

        $history = StateHistory::first();
        expect($history->from_state)->toBe('draft');
        expect($history->to_state)->toBe('review');
        expect($history->model_type)->toBe(Post::class);
        expect($history->model_id)->toBe($post->id);
        expect($history->field)->toBe('state');
    });

    it('records history with reason', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->setReason('Ready for editorial review');
        $transition->execute();

        $history = StateHistory::first();
        expect($history->reason)->toBe('Ready for editorial review');
    });

    it('records history with metadata', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->setMetadata(['priority' => 'high', 'reviewer' => 'editor@example.com']);
        $transition->execute();

        $history = StateHistory::first();
        expect($history->metadata)->toBe(['priority' => 'high', 'reviewer' => 'editor@example.com']);
    });

    it('records history with authenticated performer', function (): void {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
        $this->actingAs($user);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->execute();

        $history = StateHistory::first();
        expect($history->performer_id)->toBe($user->id);
        expect($history->performer_type)->toBe(User::class);
    });

    it('records history for multiple transitions in sequence', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Draft -> Review
        $transition1 = new Transition($post, 'state', Review::class);
        $transition1->execute();
        $post->refresh();

        // Review -> Published
        $transition2 = new Transition($post, 'state', Published::class);
        $transition2->execute();

        expect(StateHistory::count())->toBe(2);

        $histories = StateHistory::orderBy('id')->get();
        expect($histories[0]->from_state)->toBe('draft');
        expect($histories[0]->to_state)->toBe('review');
        expect($histories[1]->from_state)->toBe('review');
        expect($histories[1]->to_state)->toBe('published');
    });

    it('records transition class name in history', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->execute();

        $history = StateHistory::first();
        expect($history->transition_class)->toBe(Transition::class);
    });

    it('does not record history when disabled in config', function (): void {
        config(['laravel-stateflow.history.enabled' => false]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->execute();

        expect(StateHistory::count())->toBe(0);
    });

    it('does not record history when features.history is disabled', function (): void {
        config(['laravel-stateflow.features.history' => false]);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->execute();

        expect(StateHistory::count())->toBe(0);
    });

});

// ============================================================================
// MODEL transitionTo() RECORDS HISTORY
// ============================================================================

describe('Model transitionTo Records History', function (): void {

    it('records history via transitionTo method', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $post->transitionTo(Review::class);

        expect(StateHistory::count())->toBe(1);

        $history = StateHistory::first();
        expect($history->from_state)->toBe('draft');
        expect($history->to_state)->toBe('review');
    });

    it('records history with reason via transitionTo', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $post->transitionTo(Review::class, reason: 'Editorial approval needed');

        $history = StateHistory::first();
        expect($history->reason)->toBe('Editorial approval needed');
    });

    it('records history with metadata via transitionTo', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $post->transitionTo(Review::class, metadata: ['urgent' => true, 'category' => 'news']);

        $history = StateHistory::first();
        expect($history->metadata)->toBe(['urgent' => true, 'category' => 'news']);
    });

    it('records full workflow via transitionTo', function (): void {
        $user = User::create(['name' => 'Editor', 'email' => 'editor@example.com']);
        $this->actingAs($user);

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Complete workflow: Draft -> Review -> Published
        $post->transitionTo(Review::class, reason: 'Submitted for review');
        $post->refresh();

        $post->transitionTo(Published::class, reason: 'Approved for publication');

        expect(StateHistory::count())->toBe(2);

        $histories = StateHistory::orderBy('id')->get();

        // First transition
        expect($histories[0]->from_state)->toBe('draft');
        expect($histories[0]->to_state)->toBe('review');
        expect($histories[0]->reason)->toBe('Submitted for review');
        expect($histories[0]->performer_id)->toBe($user->id);

        // Second transition
        expect($histories[1]->from_state)->toBe('review');
        expect($histories[1]->to_state)->toBe('published');
        expect($histories[1]->reason)->toBe('Approved for publication');
    });

});

// ============================================================================
// FORCE TRANSITION RECORDS HISTORY
// ============================================================================

describe('Force Transition Records History', function (): void {

    it('records history for forced transition', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Force direct transition from draft to published (normally not allowed)
        $post->forceTransitionTo(Published::class, reason: 'Admin override');

        expect(StateHistory::count())->toBe(1);

        $history = StateHistory::first();
        expect($history->from_state)->toBe('draft');
        expect($history->to_state)->toBe('published');
        expect($history->reason)->toBe('Admin override');
    });

});

// ============================================================================
// EDGE CASES
// ============================================================================

describe('Transition History Edge Cases', function (): void {

    it('handles transition from null state', function (): void {
        // Create post without explicit state - will use default
        $post = Post::create(['title' => 'Test']);

        $transition = new Transition($post, 'state', Review::class);
        $transition->execute();

        $history = StateHistory::first();
        expect($history->from_state)->toBe('draft');
        expect($history->to_state)->toBe('review');
    });

    it('records history when state name is used instead of class', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $post->transitionTo('review');

        expect(StateHistory::count())->toBe(1);

        $history = StateHistory::first();
        expect($history->to_state)->toBe('review');
    });

    it('preserves history integrity across model refresh', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $post->transitionTo(Review::class);
        $post->refresh();
        $post->transitionTo(Published::class);

        // Reload histories from fresh query
        $histories = StateHistory::where('model_id', $post->id)
            ->where('model_type', Post::class)
            ->orderBy('id')
            ->get();

        expect($histories)->toHaveCount(2);
        expect($histories->pluck('to_state')->toArray())->toBe(['review', 'published']);
    });

});
