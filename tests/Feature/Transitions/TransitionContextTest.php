<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionContext;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
    Post::resetStateRegistration();
});

// ============================================================================
// CONTEXT CREATION TESTS
// ============================================================================

describe('TransitionContext Creation', function (): void {

    it('creates context from transition data', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            reason: 'Testing',
        );

        $context = TransitionContext::fromTransitionData($data);

        expect($context->model)->toBe($post);
        expect($context->field)->toBe('state');
        expect($context->fromState)->toBe('draft');
        expect($context->toState)->toBe('review');
        expect($context->reason)->toBe('Testing');
    });

    it('resolves state names from classes', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        // Should resolve class names to state names
        expect($context->fromState)->toBe('draft');
        expect($context->toState)->toBe('review');
    });

    it('handles string state names', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: 'draft',
            toState: 'review',
        );

        $context = TransitionContext::fromTransitionData($data);

        expect($context->fromState)->toBe('draft');
        expect($context->toState)->toBe('review');
    });

    it('sets initiated at timestamp', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $before = new DateTimeImmutable;
        $context = TransitionContext::fromTransitionData($data);
        $after = new DateTimeImmutable;

        expect($context->initiatedAt)->toBeInstanceOf(DateTimeImmutable::class);
        expect($context->initiatedAt >= $before)->toBeTrue();
        expect($context->initiatedAt <= $after)->toBeTrue();
    });

});

// ============================================================================
// HOOK TRACKING TESTS
// ============================================================================

describe('Hook Tracking', function (): void {

    it('records executed hooks', function (): void {
        $post = Post::create(['title' => 'Test']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $context->recordHook('beforeTransition');
        $context->recordHook('afterTransition');

        expect($context->hookWasExecuted('beforeTransition'))->toBeTrue();
        expect($context->hookWasExecuted('afterTransition'))->toBeTrue();
        expect($context->hookWasExecuted('onSuccess'))->toBeFalse();
    });

    it('returns all executed hooks', function (): void {
        $post = Post::create(['title' => 'Test']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $context->recordHook('validation');
        $context->recordHook('authorization');
        $context->recordHook('beforeTransition');

        $hooks = $context->getExecutedHooks();

        expect($hooks)->toBe(['validation', 'authorization', 'beforeTransition']);
    });

    it('preserves hook order', function (): void {
        $post = Post::create(['title' => 'Test']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $context->recordHook('first');
        $context->recordHook('second');
        $context->recordHook('third');

        expect($context->getExecutedHooks())->toBe(['first', 'second', 'third']);
    });

});

// ============================================================================
// CUSTOM DATA TESTS
// ============================================================================

describe('Custom Data Attachment', function (): void {

    it('attaches and retrieves custom data', function (): void {
        $post = Post::create(['title' => 'Test']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $context->attach('custom_key', 'custom_value');

        expect($context->has('custom_key'))->toBeTrue();
        expect($context->get('custom_key'))->toBe('custom_value');
    });

    it('returns default value for missing key', function (): void {
        $post = Post::create(['title' => 'Test']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        expect($context->get('nonexistent', 'default'))->toBe('default');
        expect($context->get('nonexistent'))->toBeNull();
    });

    it('attaches complex data types', function (): void {
        $post = Post::create(['title' => 'Test']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $context->attach('array_data', ['key' => 'value', 'nested' => ['a' => 1]]);
        $context->attach('object_data', (object) ['foo' => 'bar']);
        $context->attach('null_data', null);

        expect($context->get('array_data'))->toBe(['key' => 'value', 'nested' => ['a' => 1]]);
        expect($context->get('object_data'))->toEqual((object) ['foo' => 'bar']);
        expect($context->has('null_data'))->toBeTrue();
        expect($context->get('null_data'))->toBeNull();
    });

    it('returns all custom data', function (): void {
        $post = Post::create(['title' => 'Test']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        $context->attach('key1', 'value1');
        $context->attach('key2', 'value2');

        expect($context->all())->toBe(['key1' => 'value1', 'key2' => 'value2']);
    });

});

// ============================================================================
// SERIALIZATION TESTS
// ============================================================================

describe('Context Serialization', function (): void {

    it('converts to array for logging', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            reason: 'Testing',
            metadata: ['key' => 'value'],
        );

        $context = TransitionContext::fromTransitionData($data);
        $context->recordHook('beforeTransition');
        $array = $context->toArray();

        expect($array)->toHaveKeys([
            'model_type',
            'model_id',
            'field',
            'from_state',
            'to_state',
            'performer_id',
            'reason',
            'metadata',
            'initiated_at',
            'transition_class',
            'executed_hooks',
        ]);
    });

    it('includes model information in array', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);
        $array = $context->toArray();

        expect($array['model_type'])->toBe(Post::class);
        expect($array['model_id'])->toBe($post->id);
        expect($array['field'])->toBe('state');
    });

    it('includes state information in array', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);
        $array = $context->toArray();

        expect($array['from_state'])->toBe('draft');
        expect($array['to_state'])->toBe('review');
    });

    it('includes executed hooks in array', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);
        $context->recordHook('beforeTransition');
        $context->recordHook('onSuccess');
        $array = $context->toArray();

        expect($array['executed_hooks'])->toBe(['beforeTransition', 'onSuccess']);
    });

    it('formats timestamp correctly', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);
        $array = $context->toArray();

        // Should match Y-m-d H:i:s format
        expect($array['initiated_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
    });

});

// ============================================================================
// METADATA TESTS
// ============================================================================

describe('Metadata Handling', function (): void {

    it('preserves metadata from transition data', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            metadata: ['priority' => 'high', 'assigned_to' => 'user_1'],
        );

        $context = TransitionContext::fromTransitionData($data);

        expect($context->metadata)->toBe(['priority' => 'high', 'assigned_to' => 'user_1']);
    });

    it('handles empty metadata', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $context = TransitionContext::fromTransitionData($data);

        expect($context->metadata)->toBe([]);
    });

});
