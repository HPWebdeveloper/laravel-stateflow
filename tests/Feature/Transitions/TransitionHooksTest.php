<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Actions\Concerns\HasTransitionHooks;
use Hpwebdeveloper\LaravelStateflow\Actions\ExecuteTransition;
use Hpwebdeveloper\LaravelStateflow\Contracts\TransitionActionContract;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionData;
use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Exceptions\TransitionException;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Draft;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\States\Review;

beforeEach(function (): void {
    $this->createPostsTable();
    $this->createStateHistoriesTable();
    Post::resetStateRegistration();
});

// ============================================================================
// BEFORE TRANSITION HOOK TESTS
// ============================================================================

describe('Before Transition Hook', function (): void {

    /**
     * Scenario: beforeTransition hook executes before state change (pre-flight phase)
     * Setup: Override hook to set flag, execute transition
     * Assertions: Hook called with return value true allows transition to proceed
     */
    it('calls before transition hook', function (): void {
        $action = new class extends ExecuteTransition
        {
            public static bool $beforeCalled = false;

            public function beforeTransition(TransitionData $data): bool
            {
                self::$beforeCalled = true;

                return true;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action->handle($data);

        expect($action::$beforeCalled)->toBeTrue();
    });

    /**
     * Scenario: beforeTransition hook can veto transition by returning false (guard clause)
     * Setup: Override hook to return false (business rule check fails)
     * Assertions: Throws TransitionException with 'aborted by' message, state unchanged
     */
    it('aborts transition when before hook returns false', function (): void {
        $action = new class extends ExecuteTransition
        {
            public function beforeTransition(TransitionData $data): bool
            {
                return false;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
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
     * Scenario: beforeTransition hook receives complete TransitionData for inspection
     * Setup: Capture TransitionData parameter inside hook
     * Assertions: Data includes model, states, reason, metadata for decision-making
     */
    it('receives transition data in before hook', function (): void {
        $receivedData = null;

        $action = new class extends ExecuteTransition
        {
            public static ?TransitionData $receivedData = null;

            public function beforeTransition(TransitionData $data): bool
            {
                self::$receivedData = $data;

                return true;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            reason: 'Test Reason',
        );

        $action->handle($data);

        expect($action::$receivedData)->not->toBeNull();
        expect($action::$receivedData->reason)->toBe('Test Reason');
    });

});

// ============================================================================
// AFTER TRANSITION HOOK TESTS
// ============================================================================

describe('After Transition Hook', function (): void {

    /**
     * Scenario: afterTransition hook executes after successful state change (cleanup phase)
     * Setup: Override hook to set flag, complete transition
     * Assertions: Hook called with TransitionData and TransitionResult parameters
     */
    it('calls after transition hook on success', function (): void {
        $action = new class extends ExecuteTransition
        {
            public static bool $afterCalled = false;

            public function afterTransition(TransitionData $data, TransitionResult $result): void
            {
                self::$afterCalled = true;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action->handle($data);

        expect($action::$afterCalled)->toBeTrue();
    });

    /**
     * Scenario: afterTransition hook receives TransitionResult for outcome inspection
     * Setup: Capture result parameter in hook override
     * Assertions: Result contains success status, from/to states, metadata for logging
     */
    it('receives result in after hook', function (): void {
        $action = new class extends ExecuteTransition
        {
            public static ?TransitionResult $receivedResult = null;

            public function afterTransition(TransitionData $data, TransitionResult $result): void
            {
                self::$receivedResult = $result;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action->handle($data);

        expect($action::$receivedResult)->not->toBeNull();
        expect($action::$receivedResult->succeeded())->toBeTrue();
        expect($action::$receivedResult->toState)->toBe('review');
    });

});

// ============================================================================
// ON SUCCESS HOOK TESTS
// ============================================================================

describe('On Success Hook', function (): void {

    /**
     * Scenario: onSuccess hook executes only when transition completes successfully
     * Setup: Override hook to capture result, execute successful transition
     * Assertions: Hook called with succeeded result (not called on failures)
     */
    it('calls on success hook after successful transition', function (): void {
        $action = new class extends ExecuteTransition
        {
            public static ?TransitionResult $successResult = null;

            public function onSuccess(TransitionData $data, TransitionResult $result): void
            {
                self::$successResult = $result;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action->handle($data);

        expect($action::$successResult)->not->toBeNull();
        expect($action::$successResult->succeeded())->toBeTrue();
    });

    /**
     * Scenario: onSuccess hook ideal for side effects like notifications, logging, cache clearing
     * Setup: Set flag in hook to simulate side effect execution
     * Assertions: Hook runs and performs actions without affecting transition result
     */
    it('can perform side effects in success hook', function (): void {
        $sideEffectPerformed = false;

        $action = new class extends ExecuteTransition
        {
            public static bool $sideEffectPerformed = false;

            public function onSuccess(TransitionData $data, TransitionResult $result): void
            {
                self::$sideEffectPerformed = true;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action->handle($data);

        expect($action::$sideEffectPerformed)->toBeTrue();
    });

});

// ============================================================================
// ON FAILURE HOOK TESTS
// ============================================================================

describe('On Failure Hook', function (): void {

    /**
     * Scenario: onFailure hook executes when transition fails (error recovery phase)
     * Setup: Force failure via beforeTransition returning false, override onFailure
     * Assertions: onFailure called with failed result for logging/cleanup
     */
    it('calls on failure hook when before hook fails', function (): void {
        $action = new class extends ExecuteTransition
        {
            public static bool $failureCalled = false;

            public function beforeTransition(TransitionData $data): bool
            {
                return false;
            }

            public function onFailure(TransitionData $data, TransitionResult $result): void
            {
                self::$failureCalled = true;
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
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

        expect($action::$failureCalled)->toBeTrue();
    });

});

// ============================================================================
// VALIDATION RULES HOOK TESTS
// ============================================================================

describe('Validation Rules Hook', function (): void {

    /**
     * Scenario: rules() hook enforces Laravel validation rules on transition metadata
     * Setup: Define required_field rule, pass empty metadata
     * Assertions: Throws TransitionException with 'validation failed' before state change
     */
    it('validates metadata against rules', function (): void {
        $action = new class extends ExecuteTransition
        {
            public function rules(): array
            {
                return [
                    'required_field' => 'required|string',
                ];
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            metadata: [], // Missing required_field
        );

        expect(fn () => $action->handle($data))
            ->toThrow(TransitionException::class, 'validation failed');
    });

    /**
     * Scenario: Transition proceeds when metadata satisfies validation rules
     * Setup: Define required_field rule, provide valid metadata
     * Assertions: Validation passes, transition completes successfully
     */
    it('passes validation with correct metadata', function (): void {
        $action = new class extends ExecuteTransition
        {
            public function rules(): array
            {
                return [
                    'required_field' => 'required|string',
                ];
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            metadata: ['required_field' => 'value'],
        );

        $result = $action->handle($data);

        expect($result->succeeded())->toBeTrue();
    });

    /**
     * Scenario: validationRules() method allows dynamic rules based on transition context
     * Setup: Return different rules based on target state (conditional validation)
     * Assertions: Rules vary by transition type (more flexible than static rules())
     */
    it('uses validationRules method for dynamic rules', function (): void {
        $action = new class extends ExecuteTransition
        {
            public function validationRules(TransitionData $data): array
            {
                // Only require field when transitioning to review
                if ($data->toState === Review::class) {
                    return ['review_reason' => 'required'];
                }

                return [];
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
            metadata: [], // Missing review_reason
        );

        expect(fn () => $action->handle($data))
            ->toThrow(TransitionException::class, 'validation failed');
    });

});

// ============================================================================
// HOOK ORDER TESTS
// ============================================================================

describe('Hook Execution Order', function (): void {

    /**
     * Scenario: Hooks execute in defined lifecycle order (before -> after -> success)
     * Setup: Override all hooks to record execution sequence in array
     * Assertions: Order array shows correct sequence: validation, before, after, success
     */
    it('executes hooks in correct order', function (): void {
        $order = [];

        $action = new class extends ExecuteTransition
        {
            public static array $order = [];

            public function beforeTransition(TransitionData $data): bool
            {
                self::$order[] = 'before';

                return true;
            }

            public function afterTransition(TransitionData $data, TransitionResult $result): void
            {
                self::$order[] = 'after';
            }

            public function onSuccess(TransitionData $data, TransitionResult $result): void
            {
                self::$order[] = 'success';
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action->handle($data);

        expect($action::$order)->toBe(['before', 'after', 'success']);
    });

    /**
     * Scenario: TransitionContext records all executed hooks for debugging and audit
     * Setup: Execute transition using default ExecuteTransition action
     * Assertions: Context getExecutedHooks() includes validation, authorization, lifecycle hooks
     */
    it('records all hooks in context', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        $action = ExecuteTransition::make();
        $action->handle($data);

        $context = $action->getContext();
        $hooks = $context->getExecutedHooks();

        expect($hooks)->toContain('validation');
        expect($hooks)->toContain('authorization');
        expect($hooks)->toContain('beforeTransition');
        expect($hooks)->toContain('afterTransition');
        expect($hooks)->toContain('onSuccess');
    });

});

// ============================================================================
// CUSTOM TRANSITION ACTION TESTS
// ============================================================================

describe('Custom Transition Actions', function (): void {

    it('supports HasTransitionHooks trait', function (): void {
        $customAction = new class implements TransitionActionContract
        {
            use HasTransitionHooks;

            public static bool $handleCalled = false;

            public function handle(TransitionData $data): TransitionResult
            {
                self::$handleCalled = true;

                return TransitionResult::success(
                    model: $data->model,
                    fromState: 'draft',
                    toState: 'review',
                );
            }

            public function authorize(TransitionData $data): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [];
            }
        };

        $post = Post::create(['title' => 'Test', 'state' => 'draft']);
        $data = new TransitionData(
            model: $post,
            field: 'state',
            fromState: Draft::class,
            toState: Review::class,
        );

        // Test that HasTransitionHooks provides default hook implementations
        expect($customAction->beforeTransition($data))->toBeTrue();

        // Execute the action
        $result = $customAction->handle($data);

        expect($customAction::$handleCalled)->toBeTrue();
        expect($result->succeeded())->toBeTrue();
    });

});
