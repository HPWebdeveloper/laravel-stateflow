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
