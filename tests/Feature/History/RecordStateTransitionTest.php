<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Actions\RecordStateTransition;
use Hpwebdeveloper\LaravelStateflow\DTOs\StateHistoryData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Events\StateHistoryRecorded;
use Hpwebdeveloper\LaravelStateflow\Models\StateHistory;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    $this->createUsersTable();
    Post::resetStateRegistration();
});

describe('RecordStateTransition Action', function () {
    it('records transition from TransitionContext', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $context = new TransitionContext(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: null,
            reason: 'Test transition',
            metadata: ['note' => 'Testing'],
            initiatedAt: new DateTimeImmutable,
        );

        $history = RecordStateTransition::run($context);

        expect($history)->toBeInstanceOf(StateHistory::class);
        expect($history->from_state)->toBe('draft');
        expect($history->to_state)->toBe('review');
        expect($history->reason)->toBe('Test transition');
        expect($history->metadata)->toBe(['note' => 'Testing']);
    });

    it('records transition with performer', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'role' => 'editor']);

        $context = new TransitionContext(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: $user,
            reason: null,
            metadata: [],
            initiatedAt: new DateTimeImmutable,
        );

        $history = RecordStateTransition::run($context);

        expect($history->performer_id)->toBe($user->id);
        expect($history->performer_type)->toBe(User::class);
    });

    it('records transition from StateHistoryData', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: null,
            reason: 'Data test',
            metadata: ['key' => 'value'],
        );

        $action = RecordStateTransition::make();
        $history = $action->recordFromData($data);

        expect($history)->toBeInstanceOf(StateHistory::class);
        expect($history->reason)->toBe('Data test');
        expect($history->metadata)->toBe(['key' => 'value']);
    });

    it('records transition with raw parameters', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $action = RecordStateTransition::make();
        $history = $action->recordRaw(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: $user,
            reason: 'Raw test',
            metadata: ['raw' => true],
        );

        expect($history)->toBeInstanceOf(StateHistory::class);
        expect($history->performer_id)->toBe($user->id);
        expect($history->reason)->toBe('Raw test');
    });

    it('records transition from TransitionData', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $transitionData = new TransitionData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: null,
            reason: 'Transition data test',
            metadata: ['from_data' => true],
        );

        $action = RecordStateTransition::make();
        $history = $action->fromTransitionData($transitionData);

        expect($history)->toBeInstanceOf(StateHistory::class);
        expect($history->reason)->toBe('Transition data test');
    });

    it('returns null when history is disabled', function () {
        config(['laravel-stateflow.history.enabled' => false]);

        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $context = new TransitionContext(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: null,
            reason: null,
            metadata: [],
            initiatedAt: new DateTimeImmutable,
        );

        $history = RecordStateTransition::run($context);

        expect($history)->toBeNull();
        expect(StateHistory::count())->toBe(0);
    });

    it('returns null for raw when history is disabled', function () {
        config(['laravel-stateflow.history.enabled' => false]);

        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $action = RecordStateTransition::make();
        $history = $action->recordRaw(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        expect($history)->toBeNull();
    });

    it('dispatches event when configured', function () {
        Event::fake([StateHistoryRecorded::class]);
        config(['laravel-stateflow.history.dispatch_events' => true]);

        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        RecordStateTransition::make()->recordFromData($data);

        Event::assertDispatched(StateHistoryRecorded::class);
    });

    it('does not dispatch event by default', function () {
        Event::fake([StateHistoryRecorded::class]);
        config(['laravel-stateflow.history.dispatch_events' => false]);

        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        RecordStateTransition::make()->recordFromData($data);

        Event::assertNotDispatched(StateHistoryRecorded::class);
    });
});

describe('StateHistoryData DTO', function () {
    it('creates from TransitionContext', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $context = new TransitionContext(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: null,
            reason: 'Context test',
            metadata: ['context' => true],
            initiatedAt: new DateTimeImmutable,
        );

        $data = StateHistoryData::fromTransitionContext($context);

        expect($data->model)->toBe($post);
        expect($data->field)->toBe('state');
        expect($data->fromState)->toBe('draft');
        expect($data->toState)->toBe('review');
        expect($data->reason)->toBe('Context test');
    });

    it('creates from TransitionData', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $transitionData = new TransitionData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: null,
            reason: 'Data test',
            metadata: ['data' => true],
        );

        $data = StateHistoryData::fromTransitionData($transitionData);

        expect($data->model)->toBe($post);
        expect($data->reason)->toBe('Data test');
    });

    it('converts to array', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            performer: $user,
            reason: 'Array test',
            metadata: ['array' => true],
        );

        $array = $data->toArray();

        expect($array['model_type'])->toBe(Post::class);
        expect($array['model_id'])->toBe($post->id);
        expect($array['field'])->toBe('state');
        expect($array['from_state'])->toBe('draft');
        expect($array['to_state'])->toBe('review');
        expect($array['performer_id'])->toBe($user->id);
        expect($array['performer_type'])->toBe(User::class);
        expect($array['reason'])->toBe('Array test');
    });

    it('creates model from DTO', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        $model = $data->toModel();

        expect($model)->toBeInstanceOf(StateHistory::class);
        expect($model->from_state)->toBe('draft');
        expect($model->exists)->toBeFalse(); // Not saved yet
    });

    it('creates immutable copy with performer', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        $withPerformer = $data->withPerformer($user);

        expect($data->performer)->toBeNull();
        expect($withPerformer->performer)->toBe($user);
    });

    it('creates immutable copy with metadata', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
            metadata: ['original' => true],
        );

        $withMetadata = $data->withMetadata(['added' => true]);

        expect($data->metadata)->toBe(['original' => true]);
        expect($withMetadata->metadata)->toBe(['original' => true, 'added' => true]);
    });

    it('creates immutable copy with reason', function () {
        $post = Post::create(['title' => 'Test', 'state' => 'review']);

        $data = new StateHistoryData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        $withReason = $data->withReason('New reason');

        expect($data->reason)->toBeNull();
        expect($withReason->reason)->toBe('New reason');
    });
});
