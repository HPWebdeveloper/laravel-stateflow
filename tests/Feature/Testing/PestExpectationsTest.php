<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;

describe('PestExpectations', function () {
    beforeEach(function () {
        $this->createPostsTable();
    });

    describe('toBeInState', function () {
        /**
         * Scenario: Pest fluent expectation verifies model state (modern test syntax)
         * Setup: Draft post, use expect()->toBeInState('draft')
         * Assertions: Passes (Pest-style assertion for state)
         */
        it('passes when model is in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeInState('draft');
        });

        /**
         * Scenario: Expectation fails when state doesn't match (fluent error)
         * Setup: Draft post, expect published state (incorrect)
         * Assertions: Throws AssertionFailedError with readable message
         */
        it('fails when model is not in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toBeInState('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        /**
         * Scenario: Pest expectation accepts optional field parameter
         * Setup: Pass 'state' field explicitly to toBeInState()
         * Assertions: Works with custom field (supports multi-state models)
         */
        it('accepts optional field parameter', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeInState('draft', 'state');
        });
    });

    describe('toNotBeInState', function () {
        /**
         * Scenario: Pest negative expectation verifies model NOT in state
         * Setup: Draft post, expect not to be published
         * Assertions: Passes (negative fluent assertion)
         */
        it('passes when model is not in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toNotBeInState('published');
        });

        /**
         * Scenario: Negative expectation fails when state matches
         * Setup: Draft post, expect not to be draft (incorrect)
         * Assertions: Throws error (model IS in that state)
         */
        it('fails when model is in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toNotBeInState('draft'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeAbleToTransitionTo', function () {
        /**
         * Scenario: Pest fluent assertion for transition permission
         * Setup: Draft post, expect to be able to transition to review
         * Assertions: Passes (readable transition permission check)
         */
        it('passes for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeAbleToTransitionTo('review');
        });

        /**
         * Scenario: Expectation fails for disallowed transitions
         * Setup: Draft post, expect to transition to published (blocked)
         * Assertions: Throws error (transition not allowed)
         */
        it('fails for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toBeAbleToTransitionTo('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toNotBeAbleToTransitionTo', function () {
        /**
         * Scenario: Negative fluent assertion for blocked transitions
         * Setup: Draft post, expect not able to transition to published
         * Assertions: Passes (correctly identifies blocked path)
         */
        it('passes for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toNotBeAbleToTransitionTo('published');
        });

        /**
         * Scenario: Negative expectation fails when transition is allowed
         * Setup: Draft post, expect not able to transition to review (incorrect)
         * Assertions: Throws error (transition IS allowed)
         */
        it('fails for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toNotBeAbleToTransitionTo('review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toHaveAllowedTransitions', function () {
        /**
         * Scenario: Verify model has at least specified allowed transitions (subset check)
         * Setup: Draft post, check it allows ['review'] transition
         * Assertions: Passes if all specified transitions are allowed (may have more)
         */
        it('passes when all expected transitions are allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toHaveAllowedTransitions(['review']);
        });

        /**
         * Scenario: Expectation fails if any expected transition is not allowed
         * Setup: Expect ['published'] transition (not in allowed list)
         * Assertions: Throws error (transition not in allowed set)
         */
        it('fails when expected transition not allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toHaveAllowedTransitions(['published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toHaveExactlyAllowedTransitions', function () {
        /**
         * Scenario: Verify model has EXACTLY specified transitions (strict check)
         * Setup: Draft allows exactly ['review'], no more, no less
         * Assertions: Passes only if transition list matches exactly (order-independent)
         */
        it('passes when transitions match exactly', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toHaveExactlyAllowedTransitions(['review']);
        });

        it('handles multiple transitions', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            expect($post)->toHaveExactlyAllowedTransitions(['published', 'rejected']);
        });

        it('fails when transitions do not match', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toHaveExactlyAllowedTransitions(['review', 'published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeInTerminalState', function () {
        it('passes for terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            expect($post)->toBeInTerminalState();
        });

        it('fails for non-terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => expect($post)->toBeInTerminalState())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeInInitialState', function () {
        it('passes for default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)->toBeInInitialState();
        });

        it('fails for non-default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            expect(fn () => expect($post)->toBeInInitialState())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeSuccessfulTransition', function () {
        it('passes for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)->toBeSuccessfulTransition();
        });

        it('fails for failed result', function () {
            $result = TransitionResult::failure('test error');

            expect(fn () => expect($result)->toBeSuccessfulTransition())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toBeFailedTransition', function () {
        it('passes for failed result', function () {
            $result = TransitionResult::failure('test error');

            expect($result)->toBeFailedTransition();
        });

        it('fails for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => expect($result)->toBeFailedTransition())
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('can check for expected error message', function () {
            $result = TransitionResult::failure('Invalid transition');

            expect($result)->toBeFailedTransition('Invalid');
        });
    });

    describe('toHaveTransitionedTo', function () {
        it('passes when transitioned to expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)->toHaveTransitionedTo('review');
        });

        it('fails when transitioned to different state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => expect($result)->toHaveTransitionedTo('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('toHaveTransitionedFrom', function () {
        it('passes when transitioned from expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)->toHaveTransitionedFrom('draft');
        });

        it('fails when transitioned from different state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => expect($result)->toHaveTransitionedFrom('published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('chaining expectations', function () {
        it('supports chaining model expectations', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect($post)
                ->toBeInState('draft')
                ->toBeAbleToTransitionTo('review')
                ->toNotBeAbleToTransitionTo('published');
        });

        it('supports chaining result expectations', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect($result)
                ->toBeSuccessfulTransition()
                ->toHaveTransitionedFrom('draft')
                ->toHaveTransitionedTo('review');
        });
    });
});
