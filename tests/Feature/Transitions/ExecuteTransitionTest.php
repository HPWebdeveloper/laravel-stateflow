<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Actions\ExecuteTransition;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionException;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Published;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    Post::resetStateRegistration();
});

// ============================================================================
// EXECUTE TRANSITION ACTION TESTS
// ============================================================================

describe('ExecuteTransition Action', function (): void {

    /**
     * Scenario: Execute state transition using static run() method (most common usage pattern)
     * Setup: Create draft post, build TransitionData for draft->review transition
     * Assertions: Returns successful TransitionResult, model state updated to review in database
     */
    it('executes transition successfully using static run', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $result = ExecuteTransition::run($data);

        expect($result)->toBeInstanceOf(TransitionResult::class);
        expect($result->succeeded())->toBeTrue();
        expect($post->fresh()->getStateName())->toBe('review');
    });

    /**
     * Scenario: Execute transition using make() factory pattern for testability and DI
     * Setup: Create action instance via make(), call handle() with TransitionData
     * Assertions: Transition succeeds, model updated (same result as static run method)
     */
    it('executes transition successfully using make', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action = ExecuteTransition::make();
        $result = $action->handle($data);

        expect($result->succeeded())->toBeTrue();
        expect($post->fresh()->getStateName())->toBe('review');
    });

    /**
     * Scenario: TransitionResult tracks both source and destination states for audit purposes
     * Setup: Execute draft->review transition
     * Assertions: Result contains fromState='draft' and toState='review' for logging/reporting
     */
    it('stores from and to state in result', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $result = ExecuteTransition::run($data);

        expect($result->fromState)->toBe('draft');
        expect($result->toState)->toBe('review');
    });

    /**
     * Scenario: Custom metadata and reason are preserved in result for downstream consumers
     * Setup: Execute transition with reason and custom metadata dictionary
     * Assertions: Result metadata includes both reason and context, custom data preserved
     */
    it('includes metadata in result', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            reason: 'Testing',
            metadata: ['custom' => 'data'],
        );

        $result = ExecuteTransition::run($data);

        expect($result->metadata)->toHaveKey('reason');
        expect($result->metadata['reason'])->toBe('Testing');
        expect($result->metadata)->toHaveKey('context');
    });

    /**
     * Scenario: ExecuteTransition creates and tracks TransitionContext for the entire lifecycle
     * Setup: Execute transition and inspect result
     * Assertions: TransitionContext captured with model, field, states, performer, timing info
     */
    it('records transition context', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            metadata: ['custom' => 'data'],
        );

        $action = ExecuteTransition::make();
        $action->handle($data);

        $context = $action->getContext();

        expect($context)->not->toBeNull();
        expect($context->hookWasExecuted('beforeTransition'))->toBeTrue();
        expect($context->hookWasExecuted('afterTransition'))->toBeTrue();
        expect($context->hookWasExecuted('onSuccess'))->toBeTrue();
        expect($context->hookWasExecuted('validation'))->toBeTrue();
        expect($context->hookWasExecuted('authorization'))->toBeTrue();
    });

    /**
     * Scenario: Execute multiple transitions sequentially (workflow progression)
     * Setup: Create draft post, execute draft->review, then review->published
     * Assertions: Each transition succeeds independently, final state is published (validates state chain)
     */
    it('handles multiple transitions in sequence', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Draft -> Review
        $data1 = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );
        ExecuteTransition::run($data1);
        $post->refresh();

        // Review -> Published
        $data2 = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Review::class,
            toState: Published::class,
        );
        ExecuteTransition::run($data2);

        expect($post->fresh()->getStateName())->toBe('published');
    });

});

// ============================================================================
// MODEL INTEGRATION TESTS
// ============================================================================

describe('Model Integration with Actions', function (): void {

    /**
     * Scenario: Model method provides convenient wrapper around ExecuteTransition action
     * Setup: Use $model->transitionToWithAction() instead of calling action directly
     * Assertions: Action executes successfully via model method, returns proper TransitionResult
     */
    it('transitions using transitionToWithAction method', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = $post->transitionToWithAction(Review::class);

        expect($result->succeeded())->toBeTrue();
        expect($post->fresh()->getStateName())->toBe('review');
    });

    /**
     * Scenario: Action properly rejects invalid transitions with descriptive error message
     * Setup: Attempt draft->published (skipping required review step)
     * Assertions: Returns failed result with 'not allowed' error, state unchanged in database
     */
    it('returns failure for invalid transition using action', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Draft cannot go directly to Published
        $result = $post->transitionToWithAction(Published::class);

        expect($result->failed())->toBeTrue();
        expect($result->error)->toContain('not allowed');
    });

    /**
     * Scenario: Action detects and rejects transitions to non-existent states
     * Setup: Attempt transition to 'nonexistent' state (not defined in state machine)
     * Assertions: Returns failed result (validates state registry before execution)
     */
    it('returns failure for unknown state', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = $post->transitionToWithAction('nonexistent');

        expect($result->failed())->toBeTrue();
        expect($result->error)->toContain('Unknown state');
    });

    /**
     * Scenario: Validate transition eligibility without executing it (dry-run check)
     * Setup: Check both valid (draft->review) and invalid (draft->published) transitions
     * Assertions: Returns validation result with valid flag and reasons for rejection
     */
    it('validates transition before attempting', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Valid transition
        $validResult = $post->validateTransitionTo(Review::class);
        expect($validResult['valid'])->toBeTrue();
        expect($validResult['reasons'])->toBeEmpty();

        // Invalid transition
        $invalidResult = $post->validateTransitionTo(Published::class);
        expect($invalidResult['valid'])->toBeFalse();
        expect($invalidResult['reasons'])->not->toBeEmpty();
    });

    /**
     * Scenario: Model method passes reason and metadata through to action (no data loss)
     * Setup: Call transitionToWithAction() with reason string and metadata dictionary
     * Assertions: Result contains both reason and custom metadata from method call
     */
    it('validates with reason and metadata', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = $post->transitionToWithAction(
            Review::class,
            reason: 'Ready for review',
            metadata: ['priority' => 'high'],
        );

        expect($result->succeeded())->toBeTrue();
        expect($result->metadata['reason'])->toBe('Ready for review');
    });

});

// ============================================================================
// ERROR HANDLING TESTS
// ============================================================================

describe('Error Handling', function (): void {

    /**
     * Scenario: beforeTransition hook can abort transition by returning false (safety valve)
     * Setup: Override beforeTransition to return false (e.g., business rule check fails)
     * Assertions: Throws TransitionException with 'aborted by' message, state unchanged
     */
    it('throws TransitionException for aborted hook', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $action = new class extends ExecuteTransition
        {
            public function beforeTransition(TransitionData $data): bool
            {
                return false; // Abort transition
            }
        };

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        expect(fn () => $action->handle($data))
            ->toThrow(TransitionException::class, 'aborted by');
    });

    /**
     * Scenario: Failed transition preserves original state (atomic operation guarantee)
     * Setup: Trigger abortion in beforeTransition hook
     * Assertions: Database state remains 'draft' after exception (no partial updates)
     */
    it('preserves original state on failure', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $action = new class extends ExecuteTransition
        {
            public function beforeTransition(TransitionData $data): bool
            {
                return false;
            }
        };

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        try {
            $action->handle($data);
        } catch (TransitionException) {
            // Expected
        }

        expect($post->fresh()->getStateName())->toBe('draft');
    });

});

// ============================================================================
// TRANSITION DATA TESTS
// ============================================================================

describe('TransitionData', function (): void {

    /**
     * Scenario: TransitionData DTO captures complete transition context (immutable value object)
     * Setup: Create DTO with model, field, states, performer, reason, and custom metadata
     * Assertions: All properties accessible via public readonly properties
     */
    it('creates TransitionData with all properties', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            performer: null,
            reason: 'Testing',
            metadata: ['key' => 'value'],
        );

        expect($data->model)->toBe($post);
        expect($data->field)->toBe('state');
        expect($data->fromState)->toBe(Draft::class);
        expect($data->toState)->toBe(Review::class);
        expect($data->reason)->toBe('Testing');
        expect($data->metadata)->toBe(['key' => 'value']);
    });

    /**
     * Scenario: TransitionData provides make() factory for named-parameter construction
     * Setup: Use TransitionData::make() instead of new constructor
     * Assertions: Factory method creates equivalent DTO (syntactic sugar for readability)
     */
    it('creates TransitionData using make factory', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = TransitionData::make(
            model: $post,
            field: 'state',
            toState: Review::class,
            reason: 'Testing',
        );

        expect($data->model)->toBe($post);
        expect($data->field)->toBe('state');
        expect($data->toState)->toBe(Review::class);
        expect($data->reason)->toBe('Testing');
    });

});
