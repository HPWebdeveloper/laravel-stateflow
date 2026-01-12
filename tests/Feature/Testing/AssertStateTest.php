<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\DTOs\TransitionResult;
use Hpwebdeveloper\LaravelStateflow\Testing\AssertState;
use Hpwebdeveloper\LaravelStateflow\Tests\Stubs\Models\Post;

uses(AssertState::class);

describe('AssertState trait', function () {
    beforeEach(function () {
        $this->createPostsTable();
    });

    describe('assertModelInState', function () {
        /**
         * Scenario: Test helper verifies model's current state (common test assertion)
         * Setup: Create draft post, assert it's in draft state
         * Assertions: Assertion passes without throwing error (model state matches)
         */
        it('passes when model is in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertModelInState($post, 'draft');
        });

        /**
         * Scenario: Assertion fails with clear error when state doesn't match
         * Setup: Draft post, assert it's published (incorrect)
         * Assertions: Throws AssertionFailedError (test fails with helpful message)
         */
        it('fails when model is not in expected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertModelInState($post, 'published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        /**
         * Scenario: Assertion supports custom field name for multi-state models
         * Setup: Pass optional 'state' field parameter explicitly
         * Assertions: Works with specified field (defaults to 'state' if omitted)
         */
        it('accepts optional field parameter', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertModelInState($post, 'draft', 'state');
        });
    });

    describe('assertModelNotInState', function () {
        /**
         * Scenario: Verify model is NOT in specific state (negative assertion)
         * Setup: Draft post, assert it's not published
         * Assertions: Passes (draft != published)
         */
        it('passes when model is not in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertModelNotInState($post, 'published');
        });

        /**
         * Scenario: Negative assertion fails when state matches (expected not to match)
         * Setup: Draft post, assert it's not draft (incorrect)
         * Assertions: Throws error (model IS in that state)
         */
        it('fails when model is in unexpected state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertModelNotInState($post, 'draft'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertCanTransitionTo', function () {
        /**
         * Scenario: Verify transition is allowed by state machine (permission testing)
         * Setup: Draft post can transition to review
         * Assertions: Passes (draft->review is allowed path)
         */
        it('passes for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertCanTransitionTo($post, 'review');
        });

        /**
         * Scenario: Assertion fails for disallowed transitions (validates workflow)
         * Setup: Draft post attempting direct transition to published
         * Assertions: Throws error (draft->published skips required review)
         */
        it('fails for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertCanTransitionTo($post, 'published'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertCannotTransitionTo', function () {
        /**
         * Scenario: Verify transition is blocked (negative permission testing)
         * Setup: Draft post cannot transition directly to published
         * Assertions: Passes (correctly identifies blocked path)
         */
        it('passes for invalid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertCannotTransitionTo($post, 'published');
        });

        /**
         * Scenario: Negative assertion fails when transition is actually allowed
         * Setup: Draft post CAN transition to review (expected cannot)
         * Assertions: Throws error (transition is allowed contrary to assertion)
         */
        it('fails for valid transition', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertCannotTransitionTo($post, 'review'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertTransitionSucceeded', function () {
        /**
         * Scenario: Verify TransitionResult indicates success (action testing)
         * Setup: Create successful transition result
         * Assertions: Passes (result succeeded)
         */
        it('passes for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            $this->assertTransitionSucceeded($result);
        });

        /**
         * Scenario: Assertion fails when result indicates failure
         * Setup: Create failed transition result
         * Assertions: Throws error (result did not succeed)
         */
        it('fails for failed result', function () {
            $result = TransitionResult::failure('test error');

            expect(fn () => $this->assertTransitionSucceeded($result))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertTransitionFailed', function () {
        /**
         * Scenario: Verify TransitionResult indicates failure (error testing)
         * Setup: Create failed transition result with error message
         * Assertions: Passes (result correctly shows failure)
         */
        it('passes for failed result', function () {
            $result = TransitionResult::failure('test error');

            $this->assertTransitionFailed($result);
        });

        it('fails for successful result', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);
            $result = TransitionResult::success($post, 'draft', 'review');

            expect(fn () => $this->assertTransitionFailed($result))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('can check for expected error message', function () {
            $result = TransitionResult::failure('Invalid transition');

            $this->assertTransitionFailed($result, 'Invalid');

            expect(fn () => $this->assertTransitionFailed($result, 'Different error'))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertHasAllowedTransitions', function () {
        it('passes when all expected transitions are allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertHasAllowedTransitions($post, ['review']);
        });

        it('fails when expected transition not allowed', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertHasAllowedTransitions($post, ['published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertAllowedTransitionsExactly', function () {
        it('passes when transitions match exactly', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertAllowedTransitionsExactly($post, ['review']);
        });

        it('fails when transitions do not match', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertAllowedTransitionsExactly($post, ['review', 'published']))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });

        it('handles multiple transitions', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            // Review can transition to published or rejected
            $this->assertAllowedTransitionsExactly($post, ['published', 'rejected']);
        });
    });

    describe('assertNoAllowedTransitions', function () {
        it('passes for terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            // Published is a terminal state with no outgoing transitions
            $this->assertNoAllowedTransitions($post);
        });

        it('fails for non-terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertNoAllowedTransitions($post))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertInTerminalState', function () {
        it('passes for terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'published']);

            $this->assertInTerminalState($post);
        });

        it('fails for non-terminal state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            expect(fn () => $this->assertInTerminalState($post))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });

    describe('assertInInitialState', function () {
        it('passes for default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'draft']);

            $this->assertInInitialState($post);
        });

        it('fails for non-default state', function () {
            $post = Post::create(['title' => 'Test', 'state' => 'review']);

            expect(fn () => $this->assertInInitialState($post))
                ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
        });
    });
});
