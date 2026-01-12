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

    /**
     * Scenario: Create execution context from transition data DTO (start of lifecycle tracking)
     * Setup: Build TransitionData with model, field, states, reason
     * Assertions: Context captures all properties for monitoring transition execution
     */
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

    /**
     * Scenario: Context resolves state class FQCNs to normalized state names automatically
     * Setup: Pass Draft::class and Review::class (fully qualified class names)
     * Assertions: Context stores 'draft' and 'review' strings (normalized for consistency)
     */
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

    /**
     * Scenario: Context accepts pre-normalized string state names (no transformation needed)
     * Setup: Pass 'draft' and 'review' strings directly (already in canonical form)
     * Assertions: String names preserved as-is (flexibility for different input formats)
     */
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

    /**
     * Scenario: Context records precise initiation timestamp for audit and performance tracking
     * Setup: Capture time before/after context creation
     * Assertions: initiatedAt falls within measured time window (millisecond precision)
     */
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

    /**
     * Scenario: Context tracks which lifecycle hooks executed during transition (debug/audit tool)
     * Setup: Manually record hook execution via recordHook()
     * Assertions: hookWasExecuted() query returns true for recorded hooks, false otherwise
     */
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

    /**
     * Scenario: Retrieve complete list of executed hooks for comprehensive audit trail
     * Setup: Record validation, authorization, beforeTransition hooks
     * Assertions: getExecutedHooks() returns array of all recorded hook names
     */
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

    /**
     * Scenario: Hook recording maintains chronological order (critical for debugging lifecycle)
     * Setup: Record 'first', 'second', 'third' hooks sequentially
     * Assertions: Array preserves exact execution order for timeline reconstruction
     */
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

    /**
     * Scenario: Attach arbitrary data to context for communication between hooks and actions
     * Setup: Store custom key-value pair during transition lifecycle
     * Assertions: Data persists and retrievable via has()/get() methods (context as state bag)
     */
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

    /**
     * Scenario: Safe data retrieval with fallback defaults (avoid null pointer exceptions)
     * Setup: Query non-existent key with and without default value
     * Assertions: Returns provided default or null if key missing (defensive programming)
     */
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

    /**
     * Scenario: Context supports rich data types beyond primitives (not just strings)
     * Setup: Attach nested arrays, stdClass objects, null values
     * Assertions: All types preserved with correct structure (no serialization/type coercion)
     */
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

    /**
     * Scenario: Retrieve all attached custom data as dictionary (bulk export for debugging)
     * Setup: Attach multiple key-value pairs
     * Assertions: all() returns complete associative array of custom data
     */
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

    /**
     * Scenario: Serialize context to array for logging, API responses, or persistence
     * Setup: Create full context with metadata and hook tracking, record hook execution
     * Assertions: toArray() returns structured dict with model info, states, timing, hooks
     */
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

    /**
     * Scenario: Serialized context captures model type and ID for polymorphic reconstruction
     * Setup: Create context from Post model transition
     * Assertions: Array includes model_type (FQCN), model_id (PK), field name
     */
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

    /**
     * Scenario: Serialized context includes normalized state names for state timeline logging
     * Setup: Create context with Draft->Review transition
     * Assertions: Array contains from_state='draft', to_state='review' (normalized strings)
     */
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

    /**
     * Scenario: Serialized context preserves hook execution history for audit logs
     * Setup: Record beforeTransition and onSuccess hooks
     * Assertions: Array's executed_hooks contains ordered list of lifecycle phases
     */
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

    /**
     * Scenario: Timestamp formatted as MySQL-compatible datetime string for database storage
     * Setup: Create context and convert to array
     * Assertions: initiated_at matches 'Y-m-d H:i:s' format (DATETIME column compatible)
     */
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

    /**
     * Scenario: Custom metadata from TransitionData preserved in context (no data loss)
     * Setup: Pass metadata dictionary with priority and assignment info
     * Assertions: Context metadata property contains exact input data (immutable preservation)
     */
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

    /**
     * Scenario: Context handles missing metadata gracefully (no required metadata)
     * Setup: Create TransitionData without metadata parameter
     * Assertions: Context metadata defaults to empty array (not null, safe iteration)
     */
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
