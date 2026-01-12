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

    it('transitions using transitionToWithAction method', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = $post->transitionToWithAction(Review::class);

        expect($result->succeeded())->toBeTrue();
        expect($post->fresh()->getStateName())->toBe('review');
    });

    it('returns failure for invalid transition using action', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        // Draft cannot go directly to Published
        $result = $post->transitionToWithAction(Published::class);

        expect($result->failed())->toBeTrue();
        expect($result->error)->toContain('not allowed');
    });

    it('returns failure for unknown state', function (): void {
        $post = Post::create(['title' => 'Test', 'state' => 'draft']);

        $result = $post->transitionToWithAction('nonexistent');

        expect($result->failed())->toBeTrue();
        expect($result->error)->toContain('Unknown state');
    });

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
